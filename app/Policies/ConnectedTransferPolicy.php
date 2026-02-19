<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConnectedTransfer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectedTransferPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConnectedTransfer');
    }

    public function view(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('View:ConnectedTransfer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConnectedTransfer');
    }

    public function update(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('Update:ConnectedTransfer');
    }

    public function delete(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('Delete:ConnectedTransfer');
    }

    public function restore(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('Restore:ConnectedTransfer');
    }

    public function forceDelete(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('ForceDelete:ConnectedTransfer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConnectedTransfer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConnectedTransfer');
    }

    public function replicate(AuthUser $authUser, ConnectedTransfer $connectedTransfer): bool
    {
        return $authUser->can('Replicate:ConnectedTransfer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConnectedTransfer');
    }

}