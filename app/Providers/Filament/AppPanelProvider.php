<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentEmbedMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Models\Store;
use App\Filament\Resources\Shield\Roles\RoleResource;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Enums\Width;
use App\Filament\Pages\ShopifyImportTest;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->tenant(Store::class)
            ->tenantRoutePrefix('store')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                RoleResource::class, // Register before plugin so plugin detects it
            ])
            ->maxContentWidth(Width::Full)
            ->plugin(FilamentShieldPlugin::make())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                ShopifyImportTest::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
                FilamentEmbedMode::class, // Add embed mode support
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationItems([
                NavigationItem::make('Horizon')
                    ->label(__('filament.navigation.horizon'))
                    ->url('/horizon', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-chart-bar-square')
                    ->group(__('filament.navigation_groups.system'))
                    ->sort(100),
                NavigationItem::make('Pulse')
                    ->label(__('filament.navigation.pulse'))
                    ->url('/pulse', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-heart')
                    ->group(__('filament.navigation_groups.system'))
                    ->sort(101),
            ]);
    }
}
