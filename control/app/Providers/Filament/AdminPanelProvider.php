<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Livewire\StatusStrip;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->brandName('Cổng AI')
            ->brandLogo(fn () => view('brand.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            // Phông tự lưu: giao diện nội bộ không gọi CDN ngoài.
            ->font('Be Vietnam Pro', url: asset('css/gateway-fonts.css'), provider: LocalFontProvider::class)
            ->colors([
                // Xanh dương #1E5BC6 (600). Xám lạnh pha xanh để nền sáng/tối đồng nhất với thương hiệu.
                'primary' => [
                    50 => '238, 244, 255', 100 => '220, 232, 255', 200 => '185, 209, 255', 300 => '140, 177, 250',
                    400 => '92, 142, 235', 500 => '58, 116, 219', 600 => '30, 91, 198', 700 => '23, 73, 160',
                    800 => '19, 59, 128', 900 => '15, 46, 99', 950 => '9, 29, 64',
                ],
                'gray' => [
                    50 => '244, 247, 251', 100 => '234, 239, 247', 200 => '217, 226, 239', 300 => '188, 201, 221',
                    400 => '143, 161, 189', 500 => '94, 112, 142', 600 => '74, 90, 115', 700 => '52, 66, 89',
                    800 => '35, 50, 80', 900 => '16, 26, 46', 950 => '10, 18, 32',
                ],
                // Thang riêng để chữ huy hiệu (sắc 600 trên nền 50) đạt tương phản AA ở chế độ sáng.
                'success' => [
                    50 => '236, 250, 243', 100 => '208, 243, 226', 200 => '167, 232, 200', 300 => '115, 214, 165',
                    400 => '62, 189, 126', 500 => '31, 153, 95', 600 => '23, 128, 79', 700 => '19, 102, 65',
                    800 => '17, 82, 54', 900 => '14, 66, 45', 950 => '7, 40, 27',
                ],
                'warning' => [
                    50 => '255, 249, 235', 100 => '254, 240, 200', 200 => '252, 224, 150', 300 => '249, 202, 94',
                    400 => '244, 176, 50', 500 => '214, 138, 22', 600 => '152, 90, 10', 700 => '124, 72, 10',
                    800 => '102, 60, 12', 900 => '84, 50, 13', 950 => '48, 26, 5',
                ],
                'danger' => [
                    50 => '253, 240, 238', 100 => '251, 224, 220', 200 => '247, 196, 189', 300 => '241, 157, 146',
                    400 => '232, 111, 97', 500 => '212, 74, 58', 600 => '192, 57, 43', 700 => '160, 45, 34',
                    800 => '132, 40, 31', 900 => '110, 37, 30', 950 => '61, 16, 12',
                ],
                'info' => [
                    50 => '236, 250, 253', 100 => '207, 243, 250', 200 => '165, 231, 244', 300 => '103, 213, 235',
                    400 => '40, 188, 220', 500 => '14, 143, 176', 600 => '12, 118, 146', 700 => '14, 95, 118',
                    800 => '17, 78, 97', 900 => '16, 65, 82', 950 => '8, 42, 55',
                ],
            ])
            ->darkMode(true)
            ->topNavigation()
            ->breadcrumbs(false)
            ->maxContentWidth(MaxWidth::ScreenTwoExtraLarge)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Hệ thống'),
            ])
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn (): string => Blade::render('@livewire(\App\Livewire\StatusStrip::class)'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
