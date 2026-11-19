<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'a', 'email' => 'a@t.test', 'password' => 'mat-khau-thu-nghiem-12', 'role' => Role::Admin->value]);
    }

    public function test_outside_allowed_range_is_forbidden(): void
    {
        config(['gateway.admin_allowed_cidrs' => ['10.9.0.0/16']]);
        $this->actingAs($this->admin())->get('/')->assertForbidden();
        $this->get('/login')->assertForbidden();
    }

    public function test_inside_allowed_range_is_ok(): void
    {
        config(['gateway.admin_allowed_cidrs' => ['127.0.0.1/32']]);
        $this->actingAs($this->admin())->get('/')->assertOk();
    }

    public function test_production_without_config_locks_everything(): void
    {
        config(['gateway.admin_allowed_cidrs' => []]);
        $this->app['env'] = 'production';
        $this->actingAs($this->admin())->get('/')->assertForbidden();
    }
}
