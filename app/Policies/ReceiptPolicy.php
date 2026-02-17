<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Receipt;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReceiptPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Receipt');
    }

    public function view(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('View:Receipt');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Receipt');
    }

    public function update(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('Update:Receipt');
    }

    public function delete(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('Delete:Receipt');
    }

    public function restore(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('Restore:Receipt');
    }

    public function forceDelete(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('ForceDelete:Receipt');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Receipt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Receipt');
    }

    public function replicate(AuthUser $authUser, Receipt $receipt): bool
    {
        return $authUser->can('Replicate:Receipt');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Receipt');
    }
}
