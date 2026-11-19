<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Account;
use App\Models\ClientApp;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        $this->actingAs($u);

        return $u;
    }

    public static function badRunnerUrls(): array
    {
        return [
            'IP nội bộ' => ['http://10.0.0.5:8800'],
            'localhost' => ['http://localhost:8800'],
            'metadata' => ['http://169.254.169.254/'],
            'có đường dẫn' => ['http://runner-x:8800/admin'],
            'https' => ['https://runner-x:8800'],
            'tên ngoài' => ['http://evil.example.com:8800'],
            'có tài khoản' => ['http://user:pw@runner-x:8800'],
            'cổng đặc quyền' => ['http://runner-x:80'],
        ];
    }

    /** @dataProvider badRunnerUrls */
    public function test_runner_url_rejects_ssrf_targets(string $url): void
    {
        $this->admin();
        $p = Provider::create(['code' => 'claude', 'name' => 'Claude', 'color' => '#6D5BD0']);
        Livewire::test(CreateAccount::class)
            ->fillForm(['provider_id' => $p->id, 'label' => 'claude-01', 'max_concurrency' => 2, 'runner_url' => $url])
            ->call('create')->assertHasFormErrors(['runner_url']);
        $this->assertSame(0, Account::count());
    }

    public function test_account_is_created_pending_with_valid_runner_url(): void
    {
        $this->admin();
        $p = Provider::create(['code' => 'claude', 'name' => 'Claude', 'color' => '#6D5BD0']);
        Livewire::test(CreateAccount::class)
            ->fillForm(['provider_id' => $p->id, 'label' => 'claude-01', 'max_concurrency' => 2, 'runner_url' => 'http://runner-claude-01:8800'])
            ->call('create')->assertHasNoFormErrors();
        $a = Account::firstOrFail();
        $this->assertSame('pending', $a->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.create', 'subject' => 'claude-01']);
    }

    public function test_account_form_has_safe_defaults(): void
    {
        $this->admin();
        Livewire::test(CreateAccount::class)->assertFormSet(['max_concurrency' => 2]);
    }

    public function test_label_must_be_slug(): void
    {
        $this->admin();
        $p = Provider::create(['code' => 'claude', 'name' => 'Claude', 'color' => '#6D5BD0']);
        Livewire::test(CreateAccount::class)
            ->fillForm(['provider_id' => $p->id, 'label' => 'Claude 01!', 'max_concurrency' => 2, 'runner_url' => 'http://runner-x:8800'])
            ->call('create')->assertHasFormErrors(['label']);
    }

    public function test_webhook_is_encrypted_and_never_audited_in_plain(): void
    {
        $this->admin();
        $url = 'https://hooks.example.com/services/ABC123';
        Livewire::test(Settings::class)
            ->fillForm(['warn_pct' => 80, 'crit_pct' => 100, 'auth_check_minutes' => 15, 'usage_days' => 90, 'audit_days' => 365, 'emails' => ['ops@example.com'], 'webhook_url' => $url])
            ->call('save')->assertHasNoFormErrors();

        $raw = \DB::table('settings')->where('key', 'alert.webhook_url')->value('value');
        $this->assertStringNotContainsString('hooks.example.com', $raw);
        $this->assertSame($url, Setting::read('alert.webhook_url'));
        $this->assertStringNotContainsString('hooks.example.com', json_encode(\DB::table('audit_logs')->get()));
    }

    public function test_settings_blank_webhook_keeps_existing(): void
    {
        $this->admin();
        Setting::write('alert.webhook_url', 'https://hooks.example.com/x', encrypted: true);
        Livewire::test(Settings::class)
            ->fillForm(['warn_pct' => 80, 'crit_pct' => 100, 'auth_check_minutes' => 15, 'usage_days' => 90, 'audit_days' => 365, 'webhook_url' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('https://hooks.example.com/x', Setting::read('alert.webhook_url'));
    }

    public static function badWebhooks(): array
    {
        return [['http://hooks.example.com/x'], ['https://10.0.0.1/x'], ['https://localhost/x'], ['https://intranet.corp/x'], ['https://metadata.internal/x'], ['https://169.254.169.254/x']];
    }

    /** @dataProvider badWebhooks */
    public function test_webhook_rejects_internal_targets(string $url): void
    {
        $this->admin();
        Livewire::test(Settings::class)
            ->fillForm(['warn_pct' => 80, 'crit_pct' => 100, 'auth_check_minutes' => 15, 'usage_days' => 90, 'audit_days' => 365, 'webhook_url' => $url])
            ->call('save')->assertHasFormErrors(['webhook_url']);
        $this->assertFalse(Setting::has('alert.webhook_url'));
    }

    public function test_cannot_demote_last_admin_nor_delete_self(): void
    {
        $admin = $this->admin();
        $this->assertFalse(\App\Filament\Resources\UserResource::canDelete($admin));

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['role' => Role::Viewer->value])->call('save');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_can_delete_other_user_when_two_admins(): void
    {
        $admin = $this->admin();
        $other = User::create(['name' => 'b', 'email' => 'b@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
        $this->assertTrue(\App\Filament\Resources\UserResource::canDelete($other));
        $this->assertFalse(\App\Filament\Resources\UserResource::canDelete($admin));
    }

    public function test_api_key_is_stored_hashed_only(): void
    {
        $app = ClientApp::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        [$key, $plain] = \App\Models\AppKey::issue($app, 'k');
        $this->assertStringStartsWith('gw_', $plain);
        $this->assertNotSame($plain, $key->key_hash);
        $this->assertDatabaseMissing('app_keys', ['key_hash' => $plain]);
        $this->assertSame(hash_hmac('sha256', $plain, (string) config('app.key')), $key->key_hash);
    }
}
