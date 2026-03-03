<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\QuantityUnit;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuantityUnitPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QuantityUnit');
    }

    public function view(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('View:QuantityUnit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QuantityUnit');
    }

    public function update(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('Update:QuantityUnit');
    }

    public function delete(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('Delete:QuantityUnit');
    }

    public function restore(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('Restore:QuantityUnit');
    }

    public function forceDelete(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('ForceDelete:QuantityUnit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QuantityUnit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QuantityUnit');
    }

    public function replicate(AuthUser $authUser, QuantityUnit $quantityUnit): bool
    {
        return $authUser->can('Replicate:QuantityUnit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QuantityUnit');
    }

}