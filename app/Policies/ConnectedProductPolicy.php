<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectedProduct;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConnectedProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedProduct');
    }

    public function view(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('View:ConnectedProduct');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedProduct');
    }

    public function update(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('Update:ConnectedProduct');
    }

    public function delete(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('Delete:ConnectedProduct');
    }

    public function restore(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('Restore:ConnectedProduct');
    }

    public function forceDelete(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('ForceDelete:ConnectedProduct');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedProduct');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedProduct');
    }

    public function replicate(AuthUser $authUser, ConnectedProduct $connectedProduct): bool
    {
        return $authUser->can('Replicate:ConnectedProduct');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedProduct');
    }
}
