<?php

namespace App\Providers\Filament;

use App\Enums\AddonType;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Shield\Roles\RoleResource;
use App\Http\Middleware\FilamentEmbedMode;
use App\Models\Addon;
use App\Models\Store;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leek\FilamentWorkflows\Resources\WorkflowResource;
use Positiv\FilamentWebflow\WebflowPlugin;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->login()
            ->profile()
            ->databaseNotifications()
            ->tenant(Store::class)
            ->tenantRoutePrefix('store')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                RoleResource::class, // Register before plugin so plugin detects it
            ])
            ->maxContentWidth(Width::Full)
            ->plugin(FilamentShieldPlugin::make());

        if (class_exists(\Leek\FilamentWorkflows\WorkflowsPlugin::class)) {
            $panel = $panel->plugin(
                \Leek\FilamentWorkflows\WorkflowsPlugin::make()
                    ->navigationGroup(__('filament.navigation_groups.automation'))
                    ->navigation(false)
            );
        }

        $panel = $panel->plugin(WebflowPlugin::make()->navigationGroup('Webflow CMS'));

        return $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
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
            ->navigationGroups([
                __('filament.navigation_groups.pos_system'),
                __('filament.navigation_groups.catalog'),
                __('filament.navigation_groups.customers'),
                __('filament.navigation_groups.payments'),
                __('filament.navigation_groups.terminals_and_equipment'),
                __('filament.navigation_groups.settings'),
                __('filament.navigation_groups.automation'),
                __('filament.navigation_groups.system'),
                __('filament.navigation_groups.administration'),
                'Webflow CMS',
            ])
            ->navigationItems([
                NavigationItem::make('Workflows')
                    ->label('Workflows')
                    ->url(fn () => WorkflowResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-path')
                    ->group(__('filament.navigation_groups.automation'))
                    ->sort(0)
                    ->visible(fn () => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Workflows)),
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
