<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Filament\Support\Audit;
use App\Filament\Support\Perm;
use App\Services\Gateway\AccountSync;
use App\Services\Gateway\GatewayClient;
use App\Services\Gateway\LoginGuide;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

/**
 * Đăng nhập tài khoản AI ngay trên giao diện (không cần SSH).
 *
 * Luồng: gateway/runner chạy CLI đăng nhập -> trang hiện đường dẫn -> người dùng mở trên trình duyệt của mình và đăng nhập
 * (mật khẩu và 2FA nhập TRÊN TRANG CỦA HÃNG, không qua Cổng AI) -> Claude: dán mã vào ô; Codex/Grok: nhập mã thiết bị trên trang hãng, trang tự chờ.
 * MÃ XÁC NHẬN MỘT LẦN dán vào đây chỉ đi tới stdin của CLI: không lưu, không ghi log, không ghi nhật ký kiểm toán, xóa khỏi trạng thái ngay khi gửi.
 */
class LoginAccount extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AccountResource::class;

    protected static string $view = 'filament.pages.account-login';

    #[Locked]
    public ?string $sessionId = null;

    #[Locked]
    public ?string $kind = null;

    #[Locked]
    public ?string $url = null;

    #[Locked]
    public ?string $deviceCode = null;

    /** idle | awaiting_code | awaiting_device | verifying | done | error */
    #[Locked]
    public string $state = 'idle';

    #[Locked]
    public string $message = '';

    /** Mã người dùng dán. Luôn bị xóa ngay sau khi gửi (kể cả khi lỗi). */
    public string $code = '';

    public function mount(int|string $record): void
    {
        abort_unless(Perm::can('accounts.manage'), 403);
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Đăng nhập '.$this->record->label;
    }

    public function getBreadcrumbs(): array
    {
        return [AccountResource::getUrl() => 'Tài khoản AI', AccountResource::getUrl('view', ['record' => $this->record]) => $this->record->label, 'Đăng nhập'];
    }

    public function supported(): bool
    {
        return LoginGuide::supportsWeb($this->record);
    }

    public function provider(): string
    {
        return (string) ($this->record->provider?->code ?? '');
    }

    private function guard(): void
    {
        abort_unless(Perm::can('accounts.manage'), 403);
    }

    /** Áp phản hồi của gateway/runner lên trạng thái trang. */
    private function apply(array $r): void
    {
        if (! ($r['ok'] ?? false)) {
            $this->message = (string) ($r['message'] ?? 'Không thực hiện được.');

            return;
        }
        $this->sessionId = (string) ($r['session_id'] ?? $this->sessionId);      // trước khi kiểm tra đường dẫn, để còn hủy được phiên nếu đường dẫn bị chặn
        $url = LoginGuide::safeUrl($r['url'] ?? $this->url, $this->provider());
        if (($r['url'] ?? null) !== null && $url === null) {
            $this->fail('Đường dẫn đăng nhập nhận được không thuộc tên miền của hãng nên đã bị chặn.');

            return;
        }
        $this->kind = (string) ($r['kind'] ?? $this->kind);
        $this->url = $url;
        $this->deviceCode = $r['device_code'] ?? $this->deviceCode;
        $st = (string) ($r['state'] ?? '');
        $msg = (string) ($r['message'] ?? '');
        if ($msg !== '' || $st !== $this->state) {      // kiểm tra tự động không được xóa thông báo lỗi đang hiện
            $this->message = $msg;
        }
        if ($st === 'done') {
            $this->finish();
        } elseif (in_array($st, ['failed', 'cancelled'], true)) {
            $this->state = 'error';
            $this->sessionId = null;
            $this->message = $this->message !== '' ? $this->message : 'Đăng nhập chưa hoàn tất.';
        } elseif (in_array($st, ['awaiting_code', 'awaiting_device', 'verifying'], true)) {
            $this->state = $st;
        }
    }

    private function fail(string $msg): void
    {
        if ($this->sessionId) {
            app(GatewayClient::class)->loginCancel($this->record->label, $this->sessionId);
        }
        $this->state = 'error';
        $this->message = $msg;
        $this->sessionId = null;
        $this->url = $this->deviceCode = null;
    }

    public function start(): void
    {
        $this->guard();
        abort_unless($this->supported(), 404);
        $this->reset(['sessionId', 'kind', 'url', 'deviceCode', 'message', 'code']);
        $this->state = 'idle';
        Audit::log('account.login.start', $this->record->label);
        $r = app(GatewayClient::class)->loginStart($this->record->label);
        if (! ($r['ok'] ?? false)) {
            $this->state = 'error';
            $this->message = (string) ($r['message'] ?? 'Không bắt đầu được đăng nhập.');

            return;
        }
        $this->apply($r);
    }

    public function submitCode(): void
    {
        $this->guard();
        $code = trim($this->code);
        $this->code = '';                                   // xóa NGAY, trước mọi việc khác
        if ($this->state !== 'awaiting_code' || $this->sessionId === null) {
            return;
        }
        $key = 'login-code:'.auth()->id().':'.$this->record->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->message = 'Bạn gửi mã quá nhanh. Đợi một lát rồi thử lại.';

            return;
        }
        RateLimiter::hit($key, 60);
        if ($code === '') {
            $this->message = 'Hãy dán mã xác nhận vào ô.';

            return;
        }
        Audit::log('account.login.code', $this->record->label, ['lần' => RateLimiter::attempts($key)]);   // KHÔNG ghi nội dung mã
        $this->apply(app(GatewayClient::class)->loginCode($this->record->label, $this->sessionId, $code));
    }

    public function poll(): void
    {
        $this->guard();
        if ($this->sessionId === null || ! in_array($this->state, ['awaiting_code', 'awaiting_device', 'verifying'], true)) {
            return;
        }
        $r = app(GatewayClient::class)->loginStatus($this->record->label, $this->sessionId);
        if (! ($r['ok'] ?? false)) {
            $this->message = (string) ($r['message'] ?? '');
            if (($r['error_class'] ?? '') === 'bad_request') {            // phiên không còn (hết hạn hoặc bị thay)
                $this->state = 'error';
                $this->sessionId = null;
                $this->message = 'Phiên đăng nhập đã hết hạn. Hãy bắt đầu lại.';
            }

            return;
        }
        $this->apply($r);
    }

    public function cancel(): void
    {
        $this->guard();
        if ($this->sessionId !== null) {
            app(GatewayClient::class)->loginCancel($this->record->label, $this->sessionId);
        }
        Audit::log('account.login.cancel', $this->record->label);
        $this->reset(['sessionId', 'kind', 'url', 'deviceCode', 'message', 'code']);
        $this->state = 'idle';
    }

    /** CLI đã ghi token: kiểm tra thật qua runner rồi ghi nhận thời điểm đăng nhập và hạn mới. */
    private function finish(): void
    {
        $res = app(AccountSync::class)->applyCheck($this->record, app(GatewayClient::class)->check($this->record->label), recordLogin: true);
        Audit::log('account.login.done', $this->record->label, ['kiểm tra' => $res['ok'] ? 'đạt' : 'không đạt']);
        $this->sessionId = null;
        $this->url = $this->deviceCode = null;
        $this->record->refresh();
        if ($res['ok']) {
            $this->state = 'done';
            $this->message = $res['message'];
            Notification::make()->title("{$this->record->label}: đã đăng nhập")->body($res['message'])->success()->send();
        } else {
            $this->state = 'error';
            $this->message = 'CLI đã lưu phiên nhưng kiểm tra chưa đạt: '.$res['message'];
        }
    }
}
