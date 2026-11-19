<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Filament\Resources\AccountResource\Pages\ViewAccount;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Provider;
use App\Models\User;
use App\Services\Gateway\AccountSync;
use App\Services\Gateway\GatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GatewaySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gateway.demo' => false, 'gateway.url' => 'http://gw.test:8000', 'gateway.admin_token' => str_repeat('a', 40), 'gateway.admin_token_file' => null]);
    }

    /** Http::fake lần sau không ghi đè lần trước, nên đặt lại factory mỗi lần. */
    private function fake(array|callable $stubs = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
    }

    private function account(string $label = 'claude-01', AccountStatus $st = AccountStatus::Active): Account
    {
        $p = Provider::firstOrCreate(['code' => 'claude'], ['name' => 'Claude', 'color' => '#000']);

        return Account::create(['provider_id' => $p->id, 'label' => $label, 'status' => $st, 'max_concurrency' => 2, 'runner_url' => "http://runner-{$label}:8800"]);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        $this->actingAs($u);

        return $u;
    }

    public function test_gui_ma_quan_tri_va_khong_nem_loi_khi_gateway_tat(): void
    {
        $this->fake(['gw.test:8000/internal/state' => Http::response(['accounts' => []], 200)]);
        $this->assertSame([], app(GatewayClient::class)->state()['accounts']);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Admin-Token', str_repeat('a', 40)));

        $this->fake(['*' => Http::response('', 500)]);
        $this->assertNull(app(GatewayClient::class)->state());
        $this->fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->assertNull(app(GatewayClient::class)->state());
        $this->assertFalse(app(GatewayClient::class)->check('claude-01')['ok']);
    }

    public function test_khong_co_ma_quan_tri_thi_khong_goi_gateway(): void
    {
        config(['gateway.admin_token' => null]);
        $this->fake();
        $this->assertNull(app(GatewayClient::class)->state());
        $this->assertSame('no_admin_token', app(GatewayClient::class)->check('x')['error_class']);
        Http::assertNothingSent();
    }

    public function test_dong_bo_cap_nhat_slot_nghi_va_phan_tram(): void
    {
        $a = $this->account();
        $this->fake(['*/internal/state' => Http::response(['accounts' => ['claude-01' => [
            'inflight' => 1, 'cooldown_s' => 600, 'flag' => null, 'last_error' => null, 'checked_at' => 1795000000,
            'quota' => ['five_hour' => ['utilization' => 0.69, 'resets_at' => 1], 'seven_day' => ['utilization' => 0.52, 'resets_at' => 2]]]], 'counters' => []])]);
        $r = app(AccountSync::class)->run();
        $a->refresh();
        $this->assertTrue($r['ok']);
        $this->assertSame(AccountStatus::Cooldown, $a->status);
        $this->assertSame(1, $a->current_concurrency);
        $this->assertEquals(69.0, $a->pct_5h);
        $this->assertEquals(52.0, $a->pct_week);
        $this->assertSame('cli', $a->quota_source);
        $this->assertNotNull($a->cooldown_until);

        $this->fake(['*/internal/state' => Http::response(['accounts' => ['claude-01' => ['inflight' => 0, 'cooldown_s' => 0, 'flag' => null]], 'counters' => []])]);
        app(AccountSync::class)->run();
        $this->assertSame(AccountStatus::Active, $a->refresh()->status);
    }

    public function test_het_phien_mo_canh_bao_va_chi_kiem_tra_thanh_cong_moi_go(): void
    {
        $a = $this->account();
        $this->fake(['*/internal/state' => Http::response(['accounts' => ['claude-01' => ['inflight' => 0, 'cooldown_s' => 0, 'flag' => 'auth_expired']], 'counters' => []])]);
        app(AccountSync::class)->run();
        $this->assertSame(AccountStatus::AuthExpired, $a->refresh()->status);
        $this->assertSame(1, Alert::open()->where('type', 'account_auth_expired')->count());
        app(AccountSync::class)->run();
        $this->assertSame(1, Alert::open()->where('type', 'account_auth_expired')->count(), 'không mở trùng cảnh báo');

        // gateway khởi động lại -> cờ mất. Sync KHÔNG được tự coi là đã đăng nhập lại.
        $this->fake(['*/internal/state' => Http::response(['accounts' => [], 'counters' => []])]);
        app(AccountSync::class)->run();
        $this->assertSame(AccountStatus::AuthExpired, $a->refresh()->status);

        $msg = app(AccountSync::class)->applyCheck($a, ['ok' => true, 'latency_ms' => 900, 'quota' => ['five_hour' => ['utilization' => 0.1]]]);
        $this->assertTrue($msg['ok']);
        $this->assertSame(AccountStatus::Active, $a->refresh()->status);
        $this->assertEquals(10.0, $a->pct_5h);
        $this->assertSame(0, Alert::open()->where('type', 'account_auth_expired')->count());
    }

    public function test_kiem_tra_that_bai_dat_het_phien_va_tai_khoan_tam_dung_khong_bi_doi(): void
    {
        $a = $this->account();
        $r = app(AccountSync::class)->applyCheck($a, ['ok' => false, 'error_class' => 'auth_expired']);
        $this->assertFalse($r['ok']);
        $this->assertSame(AccountStatus::AuthExpired, $a->refresh()->status);

        $p = $this->account('claude-02', AccountStatus::Paused);
        $this->fake(['*/internal/state' => Http::response(['accounts' => ['claude-02' => ['inflight' => 0, 'cooldown_s' => 0, 'flag' => 'auth_expired']], 'counters' => []])]);
        app(AccountSync::class)->run();
        $this->assertSame(AccountStatus::Paused, $p->refresh()->status, 'người vận hành tạm dừng thì giữ nguyên');
    }

    public function test_gateway_mat_ket_noi_mo_canh_bao_nghiem_trong_roi_tu_dong(): void
    {
        $this->account();
        $this->fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->assertFalse(app(AccountSync::class)->run()['ok']);
        $this->assertSame(1, Alert::open()->where('type', 'gateway_unreachable')->count());
        $this->fake(['*/internal/state' => Http::response(['accounts' => [], 'counters' => []])]);
        $this->assertTrue(app(AccountSync::class)->run()['ok']);
        $this->assertSame(0, Alert::open()->where('type', 'gateway_unreachable')->count());
    }

    public function test_lenh_gateway_sync_bo_qua_o_che_do_mau(): void
    {
        config(['gateway.demo' => true]);
        $this->fake();
        $this->artisan('gateway:sync')->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_nut_kiem_tra_ngay_goi_gateway_va_ghi_nhat_ky(): void
    {
        $this->admin();
        $a = $this->account('claude-01', AccountStatus::AuthExpired);
        $this->fake(['*/internal/accounts/claude-01/check' => Http::response(['ok' => true, 'latency_ms' => 1200, 'quota' => []])]);
        Livewire::test(ViewAccount::class, ['record' => $a->getRouteKey()])->callAction('check')->assertHasNoActionErrors();
        $this->assertSame(AccountStatus::Active, $a->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.check', 'subject' => 'claude-01']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/internal/accounts/claude-01/check') && $r->method() === 'POST');
    }
}
