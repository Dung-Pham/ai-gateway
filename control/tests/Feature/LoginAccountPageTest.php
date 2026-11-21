<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Filament\Resources\AccountResource\Pages\LoginAccount;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\User;
use App\Services\Gateway\LoginGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LoginAccountPageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'MA-DANG-NHAP-BI-MAT-987654321';

    protected function setUp(): void
    {
        parent::setUp();
        config(['gateway.demo' => false, 'gateway.url' => 'http://gw.test:8000', 'gateway.admin_token' => str_repeat('a', 40), 'gateway.admin_token_file' => null]);
    }

    private function account(string $label = 'claude-01', string $prov = 'claude', AccountStatus $st = AccountStatus::AuthExpired): Account
    {
        $p = Provider::firstOrCreate(['code' => $prov], ['name' => ucfirst($prov), 'color' => '#000']);

        return Account::create(['provider_id' => $p->id, 'label' => $label, 'status' => $st, 'max_concurrency' => 2, 'runner_url' => "http://runner-{$label}:8800"]);
    }

    private function actingAsRole(Role $r = Role::Admin): User
    {
        $u = User::create(['name' => 'a', 'email' => $r->value.'@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => $r->value]);
        $this->actingAs($u);

        return $u;
    }

    private function fake(array|callable $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
    }

    private function page(Account $a)
    {
        return Livewire::test(LoginAccount::class, ['record' => $a->getRouteKey()]);
    }

    public function test_nguoi_chi_xem_khong_vao_duoc_trang_dang_nhap(): void
    {
        $a = $this->account();
        $this->actingAsRole(Role::Viewer);
        $this->get('/tai-khoan/'.$a->id.'/dang-nhap')->assertForbidden();
    }

    public function test_van_hanh_vao_duoc_trang_dang_nhap(): void
    {
        $a = $this->account();
        $this->actingAsRole(Role::Operator);
        $this->get('/tai-khoan/'.$a->id.'/dang-nhap')->assertOk()->assertSee('Bắt đầu đăng nhập');
    }

    public function test_claude_bat_dau_hien_duong_dan_va_o_dan_ma(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake(['*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/cai/oauth/authorize?code=true'])]);
        $this->page($a)->call('start')->assertSet('state', 'awaiting_code')->assertSet('kind', 'paste')
            ->assertSee('Mở trang đăng nhập')->assertSee('https://claude.com/cai/oauth/authorize?code=true')->assertSee('Dán mã');
    }

    public function test_duong_dan_la_khong_thuoc_hang_bi_chan_va_phien_bi_huy(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake(['*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://evil.example.org/phish']),
            '*/login/cancel' => Http::response(['ok' => true, 'state' => 'cancelled'])]);
        $this->page($a)->call('start')->assertSet('state', 'error')->assertSet('url', null)->assertDontSee('evil.example.org')->assertSee('bị chặn');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/login/cancel'));
    }

    public function test_dan_ma_gui_cho_gateway_xoa_khoi_trang_khong_ghi_nhat_ky_va_hoan_tat_thi_kiem_tra(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/cai/oauth/authorize?x=1']),
            '*/login/code' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'verifying']),
            '*/login/status*' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'done']),
            '*/accounts/claude-01/check' => Http::response(['ok' => true, 'latency_ms' => 300, 'auth' => ['login_expires_at' => (float) now()->addDays(25)->getTimestamp()]]),
        ]);
        $c = $this->page($a)->call('start')->set('code', self::SECRET)->call('submitCode')
            ->assertSet('code', '')->assertSet('state', 'verifying')->assertDontSee(self::SECRET);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/login/code') && $r['code'] === self::SECRET && $r['session_id'] === 'lg_1');
        $c->call('poll')->assertSet('state', 'done')->assertSee('Đăng nhập thành công');
        $a->refresh();
        $this->assertSame(AccountStatus::Active, $a->status);
        $this->assertNotNull($a->logged_in_at);
        $this->assertSame(25, $a->login_days_left);
        // mã KHÔNG nằm ở bất kỳ nhật ký kiểm toán nào
        $this->assertStringNotContainsString(self::SECRET, json_encode(AuditLog::all()->toArray()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.login.code', 'subject' => 'claude-01']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.login.done', 'subject' => 'claude-01']);
    }

    public function test_ma_sai_giu_nguyen_buoc_dan_ma_va_hien_loi(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/cai/oauth/authorize']),
            '*/login/code' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'awaiting_code', 'message' => 'Mã không đúng hoặc chưa đủ. Hãy sao chép TOÀN BỘ mã rồi dán lại.']),
        ]);
        $this->page($a)->call('start')->set('code', 'ma-sai-123456789')->call('submitCode')->assertSet('state', 'awaiting_code')->assertSet('code', '')->assertSee('Mã không đúng');
    }

    public function test_kiem_tra_tu_dong_khong_xoa_thong_bao_ma_sai(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/x']),
            '*/login/code' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'awaiting_code', 'message' => 'Mã không đúng hoặc chưa đủ.']),
            '*/login/status*' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'awaiting_code']),
        ]);
        $this->page($a)->call('start')->set('code', 'ma-sai-123456789')->call('submitCode')->call('poll')->call('poll')->assertSee('Mã không đúng');
    }

    public function test_ma_trong_va_gui_qua_nhanh(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/x']),
            '*/login/code' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'state' => 'awaiting_code', 'message' => 'sai']),
        ]);
        $c = $this->page($a)->call('start')->set('code', '   ')->call('submitCode')->assertSee('dán mã xác nhận');
        for ($i = 0; $i < 11; $i++) {
            $c->set('code', 'ma-khong-dung-'.$i.'-abcdef')->call('submitCode');
        }
        $c->assertSee('quá nhanh');
    }

    public function test_codex_hien_ma_thiet_bi_va_tu_hoan_tat_khi_poll(): void
    {
        $this->actingAsRole();
        $a = $this->account('codex-01', 'codex');
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_2', 'kind' => 'device', 'state' => 'awaiting_device', 'url' => 'https://auth.openai.com/codex/device', 'device_code' => 'ABCD-EFG12']),
            '*/login/status*' => Http::sequence()->push(['ok' => true, 'session_id' => 'lg_2', 'state' => 'awaiting_device'])->push(['ok' => true, 'session_id' => 'lg_2', 'state' => 'done']),
            '*/accounts/codex-01/check' => Http::response(['ok' => true, 'latency_ms' => 100, 'auth' => null]),
        ]);
        $c = $this->page($a)->call('start')->assertSee('ABCD-EFG12')->assertSee('https://auth.openai.com/codex/device');
        $c->call('poll')->assertSet('state', 'awaiting_device');
        $c->call('poll')->assertSet('state', 'done');
        $this->assertSame(AccountStatus::Active, $a->refresh()->status);
    }

    public function test_het_han_hoac_that_bai_hien_loi_va_cho_thu_lai(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake([
            '*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/x']),
            '*/login/status*' => Http::response(['ok' => false, 'error_class' => 'bad_request', 'message' => 'Không có phiên đăng nhập này']),
        ]);
        $this->page($a)->call('start')->call('poll')->assertSet('state', 'error')->assertSee('đã hết hạn')->assertSee('Thử lại');
    }

    public function test_gateway_khong_tra_loi_thi_bao_loi_khong_sap(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->page($a)->call('start')->assertSet('state', 'error')->assertSee('Không kết nối được gateway');
    }

    public function test_huy_xoa_phien(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->fake(['*/login/start' => Http::response(['ok' => true, 'session_id' => 'lg_1', 'kind' => 'paste', 'state' => 'awaiting_code', 'url' => 'https://claude.com/x']),
            '*/login/cancel' => Http::response(['ok' => true, 'state' => 'cancelled'])]);
        $this->page($a)->call('start')->call('cancel')->assertSet('state', 'idle')->assertSet('sessionId', null);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/login/cancel'));
    }

    public function test_agy_chua_ho_tro_huong_ve_terminal_va_khong_goi_gateway(): void
    {
        $this->actingAsRole();
        $a = $this->account('agy-01', 'agy');
        $this->fake(['*' => Http::response([])]);
        $this->assertFalse(LoginGuide::supportsWeb($a));
        $this->page($a)->assertSee('đăng nhập bằng terminal')->assertDontSee('Bắt đầu đăng nhập');
        $this->page($a)->call('start')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_thuoc_tinh_nhay_cam_khong_the_bi_sua_tu_trinh_duyet(): void
    {
        $this->actingAsRole();
        $a = $this->account();
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $this->page($a)->set('url', 'https://evil.example.org');
    }

    public function test_safe_url(): void
    {
        $this->assertNotNull(LoginGuide::safeUrl('https://claude.com/cai/oauth/authorize?x=1', 'claude'));
        $this->assertNotNull(LoginGuide::safeUrl('https://accounts.x.ai/oauth2/device?user_code=A-B', 'grok'));
        foreach (['http://claude.com/x', 'https://claude.com.evil.example.org/x', 'https://user:pw@claude.com/x', 'javascript:alert(1)', '//claude.com/x', 'https://evilclaude.com/x', null] as $bad) {
            $this->assertNull(LoginGuide::safeUrl($bad, 'claude'), (string) $bad);
        }
        $this->assertNull(LoginGuide::safeUrl('https://claude.com/x', 'codex'));          // đúng tên miền nhưng sai hãng
    }
}
