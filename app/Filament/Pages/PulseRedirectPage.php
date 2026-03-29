<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\SettingsCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\RedirectResponse;

class PulseRedirectPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'pulse';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 201;

    protected string $view = 'filament.pages.redirect-placeholder';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.pulse');
    }

    public function mount(): RedirectResponse
    {
        return redirect('/pulse');
    }
}
