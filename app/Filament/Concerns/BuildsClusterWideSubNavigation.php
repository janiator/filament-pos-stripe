<?php

namespace App\Filament\Concerns;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\Page as ResourcePage;

/**
 * Builds cluster sub-navigation links without inheriting {@see \Filament\Resources\Pages\Concerns\InteractsWithRecord::getSubNavigationParameters}
 * (e.g. the current record), so URLs for other clustered resources stay correct on edit/view pages.
 */
trait BuildsClusterWideSubNavigation
{
    /**
     * @param  array<class-string>  $components
     * @return array<int, NavigationItem>
     */
    protected function buildClusterWideSubNavigationItems(array $components): array
    {
        $parameters = [];

        $items = [];

        foreach ($components as $component) {
            $isResourcePage = is_subclass_of($component, ResourcePage::class);

            $shouldRegisterNavigation = $isResourcePage ?
                $component::shouldRegisterNavigation($parameters) :
                $component::shouldRegisterNavigation();

            if (! $shouldRegisterNavigation) {
                continue;
            }

            $canAccess = $isResourcePage ?
                $component::canAccess($parameters) :
                $component::canAccess();

            if (! $canAccess) {
                continue;
            }

            $pageItems = $isResourcePage ?
                $component::getNavigationItems($parameters) :
                $component::getNavigationItems();

            $items = [
                ...$items,
                ...$pageItems,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, NavigationItem|\Filament\Navigation\NavigationGroup>  $tail
     * @return array<int, NavigationItem|\Filament\Navigation\NavigationGroup>
     */
    protected function clusterWideSubNavigationMergedWith(array $tail): array
    {
        if (! filled($cluster = static::getCluster()) || ! $cluster::shouldRegisterSubNavigation()) {
            return $tail;
        }

        return [
            ...$this->buildClusterWideSubNavigationItems($cluster::getClusteredComponents()),
            ...$tail,
        ];
    }
}
