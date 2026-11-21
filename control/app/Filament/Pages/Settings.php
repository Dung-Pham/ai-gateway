<?php

namespace App\Filament\Pages;

use App\Filament\Support\Audit;
use App\Filament\Support\Perm;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Ngưỡng cảnh báo, giữ dữ liệu, kênh nhận cảnh báo. Địa chỉ webhook được mã hóa khi lưu và không bao giờ hiện lại.
 * Khung dựng: chưa có thành phần nào gửi cảnh báo thật (chưa có gateway), trang này chỉ lưu cấu hình.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Cài đặt';

    protected static ?string $title = 'Cài đặt';

    protected static ?string $slug = 'he-thong/cai-dat';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Perm::can('settings.manage');
    }

    public function mount(): void
    {
        $this->form->fill([
            'warn_pct' => Setting::read('alert.warn_pct', 80),
            'crit_pct' => Setting::read('alert.crit_pct', 100),
            'auth_check_minutes' => Setting::read('health.auth_check_minutes', 15),
            'usage_days' => Setting::read('retention.usage_days', 90),
            'audit_days' => Setting::read('retention.audit_days', 365),
            'emails' => Setting::read('alert.emails', []),
            'login_remind_days' => \App\Services\Gateway\LoginPolicy::remindDays(),
            'lifetime_codex' => \App\Services\Gateway\LoginPolicy::lifetimeDays('codex'),
            'lifetime_grok' => \App\Services\Gateway\LoginPolicy::lifetimeDays('grok'),
            'lifetime_agy' => \App\Services\Gateway\LoginPolicy::lifetimeDays('agy'),
            'webhook_url' => null,
            'webhook_clear' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        $hasWebhook = Setting::has('alert.webhook_url');

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Ngưỡng cảnh báo mức dùng')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TextInput::make('warn_pct')->label('Cảnh báo khi ứng dụng dùng đến (%)')->numeric()->minValue(50)->maxValue(99)->required()->suffix('%'),
                Forms\Components\TextInput::make('crit_pct')->label('Mức nghiêm trọng (%)')->numeric()->minValue(51)->maxValue(100)->required()->suffix('%')->gt('warn_pct'),
            ]),
            Forms\Components\Section::make('Kiểm tra phiên đăng nhập')->schema([
                Forms\Components\TextInput::make('auth_check_minutes')->label('Kiểm tra mỗi (phút)')->numeric()->minValue(5)->maxValue(1440)->required()->suffix('phút')
                    ->helperText('Mỗi lần kiểm tra là một lượt gọi rất nhẹ và tính vào hạn mức của tài khoản. Giá trị phù hợp chốt sau khi đo (thăm dò P2, P10).'),
            ]),
            Forms\Components\Section::make('Nhắc đăng nhập lại')->description('Phiên đăng nhập có hạn. Claude cho biết hạn thật qua CLI; hãng khác chưa cho biết nên có thể đặt hạn ước tính (ngày kể từ lần đăng nhập được ghi nhận). Để trống = không ước tính.')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TagsInput::make('login_remind_days')->label('Nhắc khi còn (ngày)')->placeholder('Gõ số rồi Enter')->nestedRecursiveRules(['integer', 'min:1', 'max:365'])->required()->columnSpanFull()
                    ->helperText('Mỗi tài khoản có đúng một cảnh báo; mức tăng dần khi gần hạn (còn từ 3 ngày trở xuống là nghiêm trọng). Mặc định 14, 7, 3, 1.'),
                Forms\Components\TextInput::make('lifetime_grok')->label('Hạn ước tính của Grok (ngày)')->numeric()->minValue(1)->maxValue(365)->suffix('ngày')->helperText('Tài liệu CLI ghi token hết hạn sau 7 ngày (chưa biết có tự làm mới không).'),
                Forms\Components\TextInput::make('lifetime_codex')->label('Hạn ước tính của Codex (ngày)')->numeric()->minValue(1)->maxValue(365)->suffix('ngày')->helperText('Chưa biết hạn thật. Chỉ đặt nếu bạn muốn nhắc định kỳ.'),
                Forms\Components\TextInput::make('lifetime_agy')->label('Hạn ước tính của Antigravity (ngày)')->numeric()->minValue(1)->maxValue(365)->suffix('ngày')->helperText('Chưa biết hạn thật. Chỉ đặt nếu bạn muốn nhắc định kỳ.'),
            ]),
            Forms\Components\Section::make('Giữ dữ liệu')->columns(['default' => 1, 'md' => 2])->schema([
                Forms\Components\TextInput::make('usage_days')->label('Nhật ký yêu cầu giữ (ngày)')->numeric()->minValue(7)->maxValue(1095)->required()->suffix('ngày'),
                Forms\Components\TextInput::make('audit_days')->label('Nhật ký kiểm toán giữ (ngày)')->numeric()->minValue(90)->maxValue(3650)->required()->suffix('ngày'),
            ]),
            Forms\Components\Section::make('Nơi nhận cảnh báo')->description('Chưa có thành phần nào gửi cảnh báo thật. Trang này chỉ lưu cấu hình để dùng khi gateway được nối.')->schema([
                Forms\Components\TagsInput::make('emails')->label('Email nhận cảnh báo')->placeholder('Gõ email rồi Enter')->nestedRecursiveRules(['email']),
                Forms\Components\TextInput::make('webhook_url')->label('Địa chỉ webhook (Slack, Telegram, Teams…)')->maxLength(500)->url()
                    ->placeholder($hasWebhook ? 'Đã đặt (ẩn). Nhập địa chỉ mới để thay thế.' : 'https://…')
                    ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                        if (blank($value)) {
                            return;
                        }
                        $u = parse_url((string) $value);
                        $host = strtolower($u['host'] ?? '');
                        // Chống SSRF: chỉ https, tên miền công khai; không IP, localhost, tên nội bộ.
                        if (($u['scheme'] ?? '') !== 'https' || $host === '' || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/(^localhost$|\.(local|internal|lan|home|corp|intranet)$)/', $host)) {
                            $fail('Chỉ nhận địa chỉ https tới một tên miền công khai (không IP, không tên nội bộ).');
                        }
                    }])
                    ->helperText('Địa chỉ này giống mật khẩu: được mã hóa khi lưu và không hiện lại. Để trống = giữ nguyên.'),
                Forms\Components\Toggle::make('webhook_clear')->label('Xóa webhook đã đặt')->visible($hasWebhook),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $d = $this->form->getState();

        Setting::write('alert.warn_pct', (int) $d['warn_pct']);
        Setting::write('alert.crit_pct', (int) $d['crit_pct']);
        Setting::write('health.auth_check_minutes', (int) $d['auth_check_minutes']);
        Setting::write('retention.usage_days', (int) $d['usage_days']);
        Setting::write('retention.audit_days', (int) $d['audit_days']);
        Setting::write('alert.emails', array_values($d['emails'] ?? []));
        Setting::write('login.remind_days', array_values(array_unique(array_map('intval', $d['login_remind_days'] ?? \App\Services\Gateway\LoginPolicy::DEFAULT_REMIND_DAYS))));
        Setting::write('login.lifetime_days', array_filter(['grok' => (int) ($d['lifetime_grok'] ?? 0), 'codex' => (int) ($d['lifetime_codex'] ?? 0), 'agy' => (int) ($d['lifetime_agy'] ?? 0)]));

        $webhook = 'giữ nguyên';
        if (! empty($d['webhook_clear'])) {
            Setting::forget('alert.webhook_url');
            $webhook = 'đã xóa';
        } elseif (filled($d['webhook_url'] ?? null)) {
            Setting::write('alert.webhook_url', $d['webhook_url'], encrypted: true);
            $webhook = 'đã đặt mới';
        }
        // Không ghi giá trị webhook vào kiểm toán.
        Audit::log('settings.update', 'cài đặt', ['webhook' => $webhook]);

        $this->mount();
        Notification::make()->title('Đã lưu cài đặt')->success()->send();
    }
}
