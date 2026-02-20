<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConnectedCustomer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectedCustomerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedCustomer');
    }

    public function view(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('View:ConnectedCustomer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedCustomer');
    }

    public function update(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('Update:ConnectedCustomer');
    }

    public function delete(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('Delete:ConnectedCustomer');
    }

    public function restore(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('Restore:ConnectedCustomer');
    }

    public function forceDelete(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('ForceDelete:ConnectedCustomer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedCustomer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedCustomer');
    }

    public function replicate(AuthUser $authUser, ConnectedCustomer $connectedCustomer): bool
    {
        return $authUser->can('Replicate:ConnectedCustomer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedCustomer');
    }

}