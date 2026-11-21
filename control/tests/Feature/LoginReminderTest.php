<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\AccountResource\Pages\ViewAccount;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Services\Gateway\AccountSync;
use App\Services\Gateway\LoginGuide;
use App\Services\Gateway\LoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LoginReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gateway.demo' => false, 'gateway.url' => 'http://gw.test:8000', 'gateway.admin_token' => str_repeat('a', 40), 'gateway.admin_token_file' => null]);
    }

    private function account(string $label = 'claude-01', string $prov = 'claude', AccountStatus $st = AccountStatus::Active): Account
    {
        $p = Provider::firstOrCreate(['code' => $prov], ['name' => ucfirst($prov), 'color' => '#000']);

        return Account::create(['provider_id' => $p->id, 'label' => $label, 'status' => $st, 'max_concurrency' => 2, 'runner_url' => "http://runner-{$label}:8800"]);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        $this->actingAs($u);

        return $u;
    }

    private function fake(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
    }

    private function state(string $label, ?float $loginExp): array
    {
        return ['accounts' => [$label => ['inflight' => 0, 'cooldown_s' => 0, 'flag' => null, 'auth' => ['login_expires_at' => $loginExp, 'access_expires_at' => null]]], 'counters' => []];
    }

    public function test_dong_bo_ghi_han_dang_nhap_tu_cli(): void
    {
        $a = $this->account();
        $exp = now()->addDays(10)->getTimestamp();
        $this->fake(['*/internal/state' => Http::response($this->state('claude-01', (float) $exp))]);
        app(AccountSync::class)->run();
        $a->refresh();
        $this->assertSame('cli', $a->login_expires_source);
        $this->assertSame(10, $a->login_days_left);
        $this->assertSame('warning', $a->login_level);
    }

    public function test_canh_bao_theo_moc_mot_canh_bao_mo_moi_tai_khoan_va_dong_khi_gia_han(): void
    {
        $a = $this->account();
        foreach ([[20, null], [10, 'warning'], [5, 'warning'], [2, 'critical'], [0, 'critical']] as [$days, $sev]) {
            $this->fake(['*/internal/state' => Http::response($this->state('claude-01', (float) now()->addDays($days)->subHour()->getTimestamp() + ($days === 0 ? -7200 : 7200)))]);
            app(AccountSync::class)->run();
            $open = Alert::open()->where('type', 'account_login_expiring')->get();
            if ($sev === null) {
                $this->assertCount(0, $open, "còn {$days} ngày: chưa nhắc");
            } else {
                $this->assertCount(1, $open, "còn {$days} ngày: đúng một cảnh báo");
                $this->assertSame($sev, $open[0]->severity->value);
            }
        }
        $this->assertStringContainsString('đã hết hạn', Alert::open()->where('type', 'account_login_expiring')->first()->title);
        // đăng nhập lại -> hạn dài thêm -> cảnh báo tự đóng
        $this->fake(['*/internal/state' => Http::response($this->state('claude-01', (float) now()->addDays(25)->getTimestamp()))]);
        app(AccountSync::class)->run();
        $this->assertSame(0, Alert::open()->where('type', 'account_login_expiring')->count());
    }

    public function test_hang_khong_cho_biet_han_thi_uoc_tinh_tu_lan_dang_nhap_va_cai_dat(): void
    {
        $g = $this->account('grok-01', 'grok');
        $this->fake(['*/internal/state' => Http::response($this->state('grok-01', null))]);
        app(AccountSync::class)->run();
        $this->assertNull($g->refresh()->login_expires_at, 'chưa ghi nhận lần đăng nhập thì không ước tính');
        $g->update(['logged_in_at' => now()->subDays(5)]);
        app(AccountSync::class)->run();
        $g->refresh();
        $this->assertSame('estimate', $g->login_expires_source);
        $this->assertSame(2, $g->login_days_left);                 // mặc định Grok 7 ngày
        $this->assertSame('critical', $g->login_level);
        $this->assertSame(1, Alert::open()->where('type', 'account_login_expiring')->count());
        // hãng không có cài đặt ước tính: không bịa hạn
        $c = $this->account('codex-01', 'codex');
        $c->update(['logged_in_at' => now()->subDays(5)]);
        $this->fake(['*/internal/state' => Http::response($this->state('codex-01', null))]);
        app(AccountSync::class)->run();
        $this->assertNull($c->refresh()->login_expires_at);
    }

    public function test_tu_het_phien_sang_dat_la_vua_dang_nhap_lai(): void
    {
        $a = $this->account('claude-01', 'claude', AccountStatus::AuthExpired);
        $exp = now()->addDays(25)->getTimestamp();
        $r = app(AccountSync::class)->applyCheck($a, ['ok' => true, 'latency_ms' => 100, 'auth' => ['login_expires_at' => (float) $exp]]);
        $a->refresh();
        $this->assertTrue($r['ok']);
        $this->assertSame(AccountStatus::Active, $a->status);
        $this->assertNotNull($a->logged_in_at);
        $this->assertSame(25, $a->login_days_left);
    }

    public function test_nut_dang_nhap_lai_kiem_tra_va_ghi_nhan_nhung_khong_nhan_bi_mat(): void
    {
        $this->admin();
        $a = $this->account('claude-01', 'claude', AccountStatus::Active);
        $this->fake(['*/internal/accounts/claude-01/check' => Http::response(['ok' => true, 'latency_ms' => 900, 'auth' => ['login_expires_at' => (float) now()->addDays(25)->getTimestamp()]])]);
        Livewire::test(ViewAccount::class, ['record' => $a->getRouteKey()])
            ->mountAction('login')->assertSee('scripts/aigw login claude claude-01')->assertSee('không nhận mật khẩu')
            ->callMountedAction()->assertHasNoActionErrors();
        $a->refresh();
        $this->assertNotNull($a->logged_in_at);
        $this->assertSame(25, $a->login_days_left);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.login', 'subject' => 'claude-01']);
    }

    public function test_cot_dang_nhap_hien_o_danh_sach_va_huong_dan_dung_hang(): void
    {
        $this->admin();
        $a = $this->account();
        $a->update(['login_expires_at' => now()->addDays(2), 'login_expires_source' => 'cli']);
        Livewire::test(ListAccounts::class)->assertSee('Đăng nhập')->assertSee('Còn 2 ngày');
        $cx = $this->account('codex-01', 'codex');
        $this->assertSame('cd /opt/aigw/ai-gateway && scripts/aigw login codex codex-01', LoginGuide::command($cx));
        $this->assertStringContainsString('MÃ THIẾT BỊ', implode(' ', LoginGuide::steps($cx)));
        $this->assertStringContainsString('Google OAuth', implode(' ', LoginGuide::steps($this->account('agy-01', 'agy'))));
    }

    public function test_cai_dat_moc_nhac_va_han_uoc_tinh_duoc_luu(): void
    {
        $this->admin();
        Livewire::test(Settings::class)->fillForm(['login_remind_days' => ['30', '10', '2'], 'lifetime_grok' => 5, 'lifetime_codex' => null, 'lifetime_agy' => 20])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame([30, 10, 2], LoginPolicy::remindDays());
        $this->assertSame(5, LoginPolicy::lifetimeDays('grok'));
        $this->assertNull(LoginPolicy::lifetimeDays('codex'));
        $this->assertSame(20, LoginPolicy::lifetimeDays('agy'));
        $this->assertSame('warning', LoginPolicy::level(20));
        $this->assertSame('ok', LoginPolicy::level(31));
    }

    public function test_mac_dinh_moc_nhac_14_7_3_1(): void
    {
        $this->assertSame([14, 7, 3, 1], LoginPolicy::remindDays());
        $this->assertSame('unknown', LoginPolicy::level(null));
        $this->assertSame('expired', LoginPolicy::level(0));
        $this->assertNull(Setting::find('login.remind_days'));
    }
}
