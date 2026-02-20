<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConnectedPaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectedPaymentMethodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedPaymentMethod');
    }

    public function view(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('View:ConnectedPaymentMethod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedPaymentMethod');
    }

    public function update(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('Update:ConnectedPaymentMethod');
    }

    public function delete(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('Delete:ConnectedPaymentMethod');
    }

    public function restore(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('Restore:ConnectedPaymentMethod');
    }

    public function forceDelete(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('ForceDelete:ConnectedPaymentMethod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedPaymentMethod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedPaymentMethod');
    }

    public function replicate(AuthUser $authUser, ConnectedPaymentMethod $connectedPaymentMethod): bool
    {
        return $authUser->can('Replicate:ConnectedPaymentMethod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedPaymentMethod');
    }

}