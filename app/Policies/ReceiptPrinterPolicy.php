<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReceiptPrinter;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReceiptPrinterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReceiptPrinter');
    }

    public function view(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('View:ReceiptPrinter');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReceiptPrinter');
    }

    public function update(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('Update:ReceiptPrinter');
    }

    public function delete(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('Delete:ReceiptPrinter');
    }

    public function restore(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('Restore:ReceiptPrinter');
    }

    public function forceDelete(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('ForceDelete:ReceiptPrinter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReceiptPrinter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReceiptPrinter');
    }

    public function replicate(AuthUser $authUser, ReceiptPrinter $receiptPrinter): bool
    {
        return $authUser->can('Replicate:ReceiptPrinter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReceiptPrinter');
    }
}
