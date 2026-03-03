<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TerminalReader;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerminalReaderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TerminalReader');
    }

    public function view(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('View:TerminalReader');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TerminalReader');
    }

    public function update(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('Update:TerminalReader');
    }

    public function delete(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('Delete:TerminalReader');
    }

    public function restore(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('Restore:TerminalReader');
    }

    public function forceDelete(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('ForceDelete:TerminalReader');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TerminalReader');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TerminalReader');
    }

    public function replicate(AuthUser $authUser, TerminalReader $terminalReader): bool
    {
        return $authUser->can('Replicate:TerminalReader');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TerminalReader');
    }

}