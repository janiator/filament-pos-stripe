<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectedPrice;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConnectedPricePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedPrice');
    }

    public function view(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('View:ConnectedPrice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedPrice');
    }

    public function update(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('Update:ConnectedPrice');
    }

    public function delete(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('Delete:ConnectedPrice');
    }

    public function restore(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('Restore:ConnectedPrice');
    }

    public function forceDelete(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('ForceDelete:ConnectedPrice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedPrice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedPrice');
    }

    public function replicate(AuthUser $authUser, ConnectedPrice $connectedPrice): bool
    {
        return $authUser->can('Replicate:ConnectedPrice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedPrice');
    }
}
