<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PosDevice;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosDevicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PosDevice');
    }

    public function view(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('View:PosDevice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PosDevice');
    }

    public function update(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('Update:PosDevice');
    }

    public function delete(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('Delete:PosDevice');
    }

    public function restore(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('Restore:PosDevice');
    }

    public function forceDelete(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('ForceDelete:PosDevice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PosDevice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PosDevice');
    }

    public function replicate(AuthUser $authUser, PosDevice $posDevice): bool
    {
        return $authUser->can('Replicate:PosDevice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PosDevice');
    }

}