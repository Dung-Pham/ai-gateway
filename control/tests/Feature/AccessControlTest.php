<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ClientApp;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ma trận quyền theo vai trò: mặc định từ chối, chủ ứng dụng chỉ thấy ứng dụng của mình. */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::create(['name' => $role->value, 'email' => "{$role->value}@t.test", 'password' => 'mat-khau-thu-nghiem-12', 'role' => $role->value]);
    }

    private function app(string $slug): ClientApp
    {
        return ClientApp::create(['name' => "App {$slug}", 'slug' => $slug, 'status' => 'active']);
    }

    public static function pagesByRole(): array
    {
        $all = ['/', '/tai-khoan', '/ung-dung', '/dinh-tuyen', '/nhat-ky', '/canh-bao', '/he-thong/kha-nang', '/he-thong/he-so-model', '/he-thong/cai-dat', '/he-thong/nguoi-dung', '/he-thong/kiem-toan', '/he-thong/dung-khan-cap', '/tai-khoan/them', '/ung-dung/them', '/dinh-tuyen/them', '/he-thong/he-so-model/them', '/he-thong/nguoi-dung/them'];
        $none = static fn (array $allowed) => array_values(array_diff($all, $allowed));

        return [
            'admin' => [Role::Admin, $all, []],
            'operator' => [Role::Operator, ['/', '/tai-khoan', '/ung-dung', '/dinh-tuyen', '/nhat-ky', '/canh-bao', '/he-thong/kha-nang', '/he-thong/he-so-model', '/he-thong/dung-khan-cap', '/tai-khoan/them', '/ung-dung/them', '/dinh-tuyen/them'], $none(['/', '/tai-khoan', '/ung-dung', '/dinh-tuyen', '/nhat-ky', '/canh-bao', '/he-thong/kha-nang', '/he-thong/he-so-model', '/he-thong/dung-khan-cap', '/tai-khoan/them', '/ung-dung/them', '/dinh-tuyen/them'])],
            'viewer' => [Role::Viewer, ['/', '/tai-khoan', '/ung-dung', '/dinh-tuyen', '/nhat-ky', '/canh-bao', '/he-thong/kha-nang', '/he-thong/he-so-model'], $none(['/', '/tai-khoan', '/ung-dung', '/dinh-tuyen', '/nhat-ky', '/canh-bao', '/he-thong/kha-nang', '/he-thong/he-so-model'])],
            'app_owner' => [Role::AppOwner, ['/ung-dung', '/nhat-ky'], $none(['/ung-dung', '/nhat-ky'])],
        ];
    }

    /** @dataProvider pagesByRole */
    public function test_role_can_only_open_its_pages(Role $role, array $allowed, array $denied): void
    {
        $u = $this->user($role);
        if ($role === Role::AppOwner) {
            $u->apps()->attach($this->app('cua-toi')->id);
        }
        $this->actingAs($u);

        foreach ($allowed as $url) {
            $this->get($url)->assertOk("{$role->value} phải mở được {$url}");
        }
        foreach ($denied as $url) {
            $this->get($url)->assertForbidden("{$role->value} KHÔNG được mở {$url}");
        }
    }

    public function test_unknown_state_denies_everything(): void
    {
        $this->get('/tai-khoan')->assertRedirect('/login');
        $this->assertFalse(Role::Viewer->can('accounts.manage'));
        $this->assertFalse(Role::Operator->can('users.manage'));
        $this->assertFalse(Role::AppOwner->can('accounts.view'));
        $this->assertTrue(Role::Admin->can('bat-ky-quyen-nao'));
    }

    public function test_app_owner_cannot_see_or_edit_other_apps(): void
    {
        $mine = $this->app('cua-toi');
        $other = $this->app('cua-nguoi-khac');
        $u = $this->user(Role::AppOwner);
        $u->apps()->attach($mine->id);
        $this->actingAs($u);

        $this->get("/ung-dung/{$mine->id}")->assertOk();
        $this->get("/ung-dung/{$other->id}")->assertNotFound();
        $this->get("/ung-dung/{$mine->id}/sua")->assertForbidden();
        // Ứng dụng của người khác: 404 (không lộ là có tồn tại), không phải 403.
        $this->get("/ung-dung/{$other->id}/sua")->assertNotFound();
    }

    public function test_app_owner_logs_are_scoped_to_own_apps(): void
    {
        $mine = $this->app('cua-toi');
        $other = $this->app('cua-nguoi-khac');
        $provider = \App\Models\Provider::create(['code' => 'claude', 'name' => 'Claude', 'color' => '#6D5BD0']);
        $acc = \App\Models\Account::create(['provider_id' => $provider->id, 'label' => 'claude-01', 'status' => 'active']);
        foreach ([[$mine, 'req_cua_toi'], [$other, 'req_nguoi_khac']] as [$app, $rid]) {
            UsageEvent::create(['ts' => now(), 'app_id' => $app->id, 'account_id' => $acc->id, 'capability' => 'text', 'model' => 'sonnet', 'status' => 'ok', 'request_id' => $rid]);
        }
        $u = $this->user(Role::AppOwner);
        $u->apps()->attach($mine->id);
        $this->actingAs($u);

        $ids = \App\Filament\Resources\UsageEventResource::getEloquentQuery()->pluck('request_id')->all();
        $this->assertSame(['req_cua_toi'], $ids);
    }

    public function test_app_owner_with_no_apps_sees_nothing(): void
    {
        $this->app('a');
        $u = $this->user(Role::AppOwner);
        $this->actingAs($u);
        $this->assertSame(0, \App\Filament\Resources\ClientAppResource::getEloquentQuery()->count());
    }

    public function test_user_without_valid_role_cannot_enter_panel(): void
    {
        $u = User::create(['name' => 'x', 'email' => 'x@t.test', 'password' => 'mat-khau-thu-nghiem-12']);
        $this->actingAs($u)->get('/')->assertForbidden();
    }
}
