<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shield\Roles\Pages;

use App\Filament\Resources\Shield\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ViewRole as BaseViewRole;

class ViewRole extends BaseViewRole
{
    protected static string $resource = RoleResource::class;
}
