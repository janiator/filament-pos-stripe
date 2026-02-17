<?php

namespace Positiv\FilamentWebflow\Filament\Pages;

use App\Enums\AddonType;
use App\Models\Addon;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;
use Positiv\FilamentWebflow\Models\WebflowSite;

class WebflowSitesNavigationPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament-panels::pages.page';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return false;
        }

        return Addon::query()
            ->where('store_id', $tenant->getKey())
            ->where('is_active', true)
            ->whereIn('type', AddonType::typesWithWebflow())
            ->exists();
    }

    /**
     * Register Webflow sites as parent nav items with active collections as children.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return [];
        }

        $sites = WebflowSite::query()
            ->whereHas('addon', fn ($q) => $q->where('store_id', $tenant->getKey()))
            ->where('is_active', true)
            ->with(['collections' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        if ($sites->isEmpty()) {
            return [];
        }

        $siteEditUrlByKey = [];
        foreach ($sites as $site) {
            $siteEditUrlByKey[$site->id] = rtrim(
                WebflowSiteResource::getUrl('edit', ['record' => $site], true, null, $tenant),
                '/'
            );
        }

        $group = 'Webflow CMS';
        $items = [];
        $sort = 50;

        foreach ($sites as $site) {
            $siteLabel = $site->name;
            $siteEditUrl = $siteEditUrlByKey[$site->id] ?? '';
            $items[] = NavigationItem::make($siteLabel)
                ->group($group)
                ->icon('heroicon-o-globe-alt')
                ->url(WebflowSiteResource::getUrl('edit', ['record' => $site], true, null, $tenant))
                ->isActiveWhen(fn (): bool => $siteEditUrl !== '' && rtrim(request()->url(), '/') === $siteEditUrl)
                ->sort($sort++);

            foreach ($site->collections as $collection) {
                $items[] = NavigationItem::make($collection->name)
                    ->group($group)
                    ->parentItem($siteLabel)
                    ->icon('heroicon-o-square-3-stack-3d')
                    ->url(WebflowCollectionItemsPage::getUrl(['collection' => $collection->id], true, null, $tenant))
                    ->isActiveWhen(fn (): bool => (string) request()->query('collection') === (string) $collection->id)
                    ->sort($sort++);
            }
        }

        return $items;
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'webflow-sites-nav';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Webflow';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
