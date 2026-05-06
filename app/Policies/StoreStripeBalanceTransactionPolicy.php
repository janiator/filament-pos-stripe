<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreStripeBalanceTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoreStripeBalanceTransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StoreStripeBalanceTransaction');
    }

    public function view(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('View:StoreStripeBalanceTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StoreStripeBalanceTransaction');
    }

    public function update(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('Update:StoreStripeBalanceTransaction');
    }

    public function delete(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('Delete:StoreStripeBalanceTransaction');
    }

    public function restore(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('Restore:StoreStripeBalanceTransaction');
    }

    public function forceDelete(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('ForceDelete:StoreStripeBalanceTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StoreStripeBalanceTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StoreStripeBalanceTransaction');
    }

    public function replicate(AuthUser $authUser, StoreStripeBalanceTransaction $storeStripeBalanceTransaction): bool
    {
        return $authUser->can('Replicate:StoreStripeBalanceTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StoreStripeBalanceTransaction');
    }
}
