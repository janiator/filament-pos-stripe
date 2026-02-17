<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PosSession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PosSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PosSession');
    }

    public function view(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('View:PosSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PosSession');
    }

    public function update(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('Update:PosSession');
    }

    public function delete(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('Delete:PosSession');
    }

    public function restore(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('Restore:PosSession');
    }

    public function forceDelete(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('ForceDelete:PosSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PosSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PosSession');
    }

    public function replicate(AuthUser $authUser, PosSession $posSession): bool
    {
        return $authUser->can('Replicate:PosSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PosSession');
    }
}
