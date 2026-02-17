<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Workflow;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Workflow');
    }

    public function view(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('View:Workflow');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Workflow');
    }

    public function update(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('Update:Workflow');
    }

    public function delete(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('Delete:Workflow');
    }

    public function restore(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('Restore:Workflow');
    }

    public function forceDelete(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('ForceDelete:Workflow');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Workflow');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Workflow');
    }

    public function replicate(AuthUser $authUser, Workflow $workflow): bool
    {
        return $authUser->can('Replicate:Workflow');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Workflow');
    }
}
