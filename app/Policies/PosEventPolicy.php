<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PosEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosEventPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PosEvent');
    }

    public function view(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('View:PosEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PosEvent');
    }

    public function update(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('Update:PosEvent');
    }

    public function delete(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('Delete:PosEvent');
    }

    public function restore(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('Restore:PosEvent');
    }

    public function forceDelete(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('ForceDelete:PosEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PosEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PosEvent');
    }

    public function replicate(AuthUser $authUser, PosEvent $posEvent): bool
    {
        return $authUser->can('Replicate:PosEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PosEvent');
    }

}