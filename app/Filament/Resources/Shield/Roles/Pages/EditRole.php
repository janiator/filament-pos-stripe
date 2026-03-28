<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shield\Roles\Pages;

use App\Filament\Resources\Shield\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as BaseEditRole;

class EditRole extends BaseEditRole
{
    protected static string $resource = RoleResource::class;
}
