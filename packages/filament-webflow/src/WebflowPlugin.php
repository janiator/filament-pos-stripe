<?php

namespace Positiv\FilamentWebflow;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;

class WebflowPlugin implements Plugin
{
    protected ?string $navigationGroup = 'Webflow CMS';

    protected ?string $tenantColumn = 'store_id';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-webflow';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                WebflowSiteResource::class,
            ])
            ->pages([
                \Positiv\FilamentWebflow\Filament\Pages\WebflowCollectionItemsPage::class,
                \Positiv\FilamentWebflow\Filament\Pages\WebflowItemEditPage::class,
                \Positiv\FilamentWebflow\Filament\Pages\WebflowSitesNavigationPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Dynamic collection resources are registered in WebflowSiteResource or via a boot callback
        // after collections are loaded. For now the plugin only registers WebflowSiteResource.
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function tenantColumn(?string $column): static
    {
        $this->tenantColumn = $column;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getTenantColumn(): ?string
    {
        return $this->tenantColumn;
    }
}
