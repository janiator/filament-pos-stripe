<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\RedirectResponse;

class HorizonRedirectPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'horizon';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 200;

    protected string $view = 'filament.pages.redirect-placeholder';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.horizon');
    }

    public function mount(): RedirectResponse
    {
        return new RedirectResponse(url('/horizon'));
    }
}
