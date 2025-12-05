<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentEmbedPanelStyles;
use App\Models\Store;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Enums\Width;

class EmbedPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('embed')
            ->path('embed')
            ->login() // Keep login for token-based auth
            ->tenant(Store::class)
            ->tenantRoutePrefix('store')
            ->colors([
                'primary' => Color::Amber,
            ])
            // Share the same resources as the main panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // No widgets for minimal UI
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)
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
                FilamentEmbedPanelStyles::class, // Inject styles to hide navigation
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
