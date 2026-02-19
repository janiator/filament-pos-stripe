<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConnectedCharge;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectedChargePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedCharge');
    }

    public function view(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('View:ConnectedCharge');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedCharge');
    }

    public function update(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('Update:ConnectedCharge');
    }

    public function delete(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('Delete:ConnectedCharge');
    }

    public function restore(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('Restore:ConnectedCharge');
    }

    public function forceDelete(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('ForceDelete:ConnectedCharge');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedCharge');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedCharge');
    }

    public function replicate(AuthUser $authUser, ConnectedCharge $connectedCharge): bool
    {
        return $authUser->can('Replicate:ConnectedCharge');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedCharge');
    }

}