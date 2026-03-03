<?php

namespace App\Filament\Pages;

use App\Enums\AddonType;
use App\Models\Addon;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;

class AddonsPage extends Page
{
    protected static ?string $slug = 'add-ons';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.settings');
    }

    protected static ?string $title = 'Add-ons';

    protected static ?string $navigationLabel = 'Add-ons';

    protected ?string $heading = 'Add-ons';

    protected ?string $subheading = 'Enable features for this store. Each add-on type can be enabled once.';

    protected string $view = 'filament.pages.addons-page';

    /**
     * Addon records for the current tenant, keyed by type value.
     *
     * @var array<string, Addon>
     */
    public array $addonsByType = [];

    public function mount(): void
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant) {
            return;
        }

        $this->addonsByType = Addon::query()
            ->where('store_id', $tenant->getKey())
            ->get()
            ->keyBy(fn (Addon $a) => $a->type->value)
            ->all();
    }

    /**
     * @return array<int, array{type: AddonType, addon: Addon|null, webflowSitesCount: int}>
     */
    public function getAddonTypesWithStatus(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant) {
            return [];
        }

        $result = [];
        foreach (AddonType::cases() as $type) {
            $addon = $this->addonsByType[$type->value] ?? null;
            $webflowSitesCount = 0;
            if ($addon && in_array($type->value, AddonType::typesWithWebflow(), true)) {
                $webflowSitesCount = $tenant->webflowSites()->count();
            }
            $result[] = [
                'type' => $type,
                'addon' => $addon,
                'webflowSitesCount' => $webflowSitesCount,
            ];
        }

        return $result;
    }

    public function enableAddon(string $typeValue): void
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant) {
            return;
        }

        $type = AddonType::tryFrom($typeValue);
        if (! $type) {
            return;
        }

        if (isset($this->addonsByType[$typeValue])) {
            $addon = $this->addonsByType[$typeValue];
            $addon->update(['is_active' => true]);
            $this->refreshAddons();
            Notification::make()
                ->title($type->label().' enabled')
                ->success()
                ->send();
        } else {
            Addon::query()->create([
                'store_id' => $tenant->getKey(),
                'type' => $type,
                'is_active' => true,
            ]);
            $this->refreshAddons();
            Notification::make()
                ->title($type->label().' enabled')
                ->body(in_array($type->value, AddonType::typesWithWebflow(), true)
                    ? 'Connect a Webflow site via Webflow CMS → Webflow Sites to get started.'
                    : null)
                ->success()
                ->send();
        }
    }

    public function disableAddon(string $typeValue): void
    {
        $addon = $this->addonsByType[$typeValue] ?? null;
        if (! $addon) {
            return;
        }

        $addon->update(['is_active' => false]);
        $this->refreshAddons();
        Notification::make()
            ->title($addon->type->label().' disabled')
            ->success()
            ->send();
    }

    private function refreshAddons(): void
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant) {
            return;
        }

        $this->addonsByType = Addon::query()
            ->where('store_id', $tenant->getKey())
            ->get()
            ->keyBy(fn (Addon $a) => $a->type->value)
            ->all();
    }

    public function getWebflowSitesUrl(): string
    {
        return WebflowSiteResource::getUrl('index');
    }

    public function getWebflowSiteCreateUrl(): string
    {
        return WebflowSiteResource::getUrl('create');
    }

    public function getEventTicketsUrl(): string
    {
        return \App\Filament\Resources\EventTickets\EventTicketResource::getUrl('index');
    }

    /**
     * Primary action when add-on is on: single "Open X" link, or null for types that use custom actions (e.g. Webflow).
     *
     * @return array{url: string, label: string}|null
     */
    public function getPrimaryActionForType(AddonType $type): ?array
    {
        return match ($type) {
            AddonType::EventTickets => [
                'url' => \App\Filament\Resources\EventTickets\EventTicketResource::getUrl('index'),
                'label' => 'Open Event Tickets',
            ],
            AddonType::GiftCards => [
                'url' => \App\Filament\Resources\GiftCards\GiftCardResource::getUrl('index'),
                'label' => 'Open Gift Cards',
            ],
            AddonType::PaymentLinks => [
                'url' => \App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource::getUrl('index'),
                'label' => 'Open Payment Links',
            ],
            AddonType::Transfers => [
                'url' => \App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource::getUrl('index'),
                'label' => 'Open Transfers',
            ],
            AddonType::Workflows => [
                'url' => \Leek\FilamentWorkflows\Resources\WorkflowResource::getUrl('index'),
                'label' => 'Open Workflows',
            ],
            AddonType::Pos => [
                'url' => \App\Filament\Resources\PosSessions\PosSessionResource::getUrl('index'),
                'label' => 'Open POS',
            ],
            AddonType::WebflowCms => null,
        };
    }

    public function typesWithWebflow(): array
    {
        return AddonType::typesWithWebflow();
    }
}
