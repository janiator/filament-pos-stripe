<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReceiptTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReceiptTemplate');
    }

    public function view(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('View:ReceiptTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReceiptTemplate');
    }

    public function update(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('Update:ReceiptTemplate');
    }

    public function delete(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('Delete:ReceiptTemplate');
    }

    public function restore(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('Restore:ReceiptTemplate');
    }

    public function forceDelete(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('ForceDelete:ReceiptTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReceiptTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReceiptTemplate');
    }

    public function replicate(AuthUser $authUser, ReceiptTemplate $receiptTemplate): bool
    {
        return $authUser->can('Replicate:ReceiptTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReceiptTemplate');
    }

}