<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PowerOfficeIntegration;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PowerOfficeIntegrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PowerOfficeIntegration');
    }

    public function view(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('View:PowerOfficeIntegration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PowerOfficeIntegration');
    }

    public function update(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('Update:PowerOfficeIntegration');
    }

    public function delete(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('Delete:PowerOfficeIntegration');
    }

    public function restore(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('Restore:PowerOfficeIntegration');
    }

    public function forceDelete(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('ForceDelete:PowerOfficeIntegration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PowerOfficeIntegration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PowerOfficeIntegration');
    }

    public function replicate(AuthUser $authUser, PowerOfficeIntegration $powerOfficeIntegration): bool
    {
        return $authUser->can('Replicate:PowerOfficeIntegration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PowerOfficeIntegration');
    }
}
