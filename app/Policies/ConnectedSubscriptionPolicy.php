<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectedSubscription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConnectedSubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedSubscription');
    }

    public function view(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('View:ConnectedSubscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedSubscription');
    }

    public function update(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('Update:ConnectedSubscription');
    }

    public function delete(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('Delete:ConnectedSubscription');
    }

    public function restore(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('Restore:ConnectedSubscription');
    }

    public function forceDelete(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('ForceDelete:ConnectedSubscription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedSubscription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedSubscription');
    }

    public function replicate(AuthUser $authUser, ConnectedSubscription $connectedSubscription): bool
    {
        return $authUser->can('Replicate:ConnectedSubscription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedSubscription');
    }
}
