<?php

namespace App\Livewire;

use App\Enums\AccountStatus;
use App\Enums\AppStatus;
use App\Models\Alert;
use App\Models\Account;
use App\Models\ClientApp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Dải trạng thái toàn hệ thống, hiện ở mọi trang (docs/GIAO_DIEN.md mục 2). */
class StatusStrip extends Component
{
    public function render(): View
    {
        $counts = Account::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('livewire.status-strip', [
            'active' => (int) ($counts[AccountStatus::Active->value] ?? 0),
            'cooldown' => (int) ($counts[AccountStatus::Cooldown->value] ?? 0),
            'expired' => (int) ($counts[AccountStatus::AuthExpired->value] ?? 0),
            'paused' => (int) (($counts[AccountStatus::Paused->value] ?? 0) + ($counts[AccountStatus::Draining->value] ?? 0)),
            'apps' => ClientApp::where('status', AppStatus::Active->value)->count(),
            'openAlerts' => Alert::open()->count(),
            'demo' => (bool) config('gateway.demo'),
            'sync' => \Illuminate\Support\Facades\Cache::get(\App\Services\Gateway\AccountSync::CACHE_KEY),
        ]);
    }
}
