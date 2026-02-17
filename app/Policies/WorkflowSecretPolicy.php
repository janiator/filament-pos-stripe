<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkflowSecret;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WorkflowSecretPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkflowSecret');
    }

    public function view(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('View:WorkflowSecret');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowSecret');
    }

    public function update(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('Update:WorkflowSecret');
    }

    public function delete(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('Delete:WorkflowSecret');
    }

    public function restore(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('Restore:WorkflowSecret');
    }

    public function forceDelete(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('ForceDelete:WorkflowSecret');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WorkflowSecret');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WorkflowSecret');
    }

    public function replicate(AuthUser $authUser, WorkflowSecret $workflowSecret): bool
    {
        return $authUser->can('Replicate:WorkflowSecret');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WorkflowSecret');
    }
}
