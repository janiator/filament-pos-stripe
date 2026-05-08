<?php

namespace App\Providers\Filament;

use App\Enums\AddonType;
use App\Filament\Pages\Dashboard;
use App\Filament\Plugins\AppWorkflowsPlugin;
use App\Filament\Resources\ProductDeclarations\ProductDeclarationResource;
use App\Filament\Resources\Shield\Roles\RoleResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Http\Middleware\FilamentEmbedMode;
use App\Models\Addon;
use App\Models\Store;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
            ->searchableTenantMenu()
            ->tenantRoutePrefix('store')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                RoleResource::class, // Register before plugin so plugin detects it
            ])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->plugin(FilamentShieldPlugin::make());

        if (class_exists(\Leek\FilamentWorkflows\WorkflowsPlugin::class)) {
            $panel = $panel->plugin(
                AppWorkflowsPlugin::make()
                    ->navigationGroup(__('filament.navigation_groups.settings'))
            );
        }

        $panel = $panel->plugin(WebflowPlugin::make()->navigationGroup(__('Webflow CMS')));

        return $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
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
                __('filament.navigation_groups.settings'),
                __('filament.navigation_groups.system'),
                __('filament.navigation_groups.administration'),
                __('Webflow CMS'),
            ])
            ->tenantMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label(__('filament.resources.store.tenant_menu'))
                    ->url(fn (): string => StoreResource::getNavigationUrl())
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->visible(fn (): bool => StoreResource::canViewAny()),
            ])
            ->userMenuItems([
                Action::make('productDeclaration')
                    ->label(__('Product declaration'))
                    ->url(fn (): string => ProductDeclarationResource::getUrl('index'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Pos)
                        && ProductDeclarationResource::canViewAny()),
            ]);
    }
}
