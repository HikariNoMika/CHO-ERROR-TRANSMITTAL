<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * A user is never removed: their name is stamped on records, templates and
     * audit rows as created_by / generated_by, and deleting the row would either
     * break those references or leave them pointing at nobody. Access is
     * withdrawn by deactivating instead.
     */
    public function delete(User $user, User $target): bool
    {
        return false;
    }

    /**
     * An account may not strip its own admin rights: with no other admin left,
     * nobody could reach this page to undo it, and the app would be locked.
     */
    public function changeRole(User $user, User $target): bool
    {
        return $this->update($user, $target) && ! ($user->is($target) && $target->isAdmin());
    }

    /** The last active admin must keep their access, for the same reason. */
    public function deactivate(User $user, User $target): bool
    {
        if (! $this->update($user, $target)) {
            return false;
        }

        if (! $target->isAdmin() || ! $target->is_active) {
            return true;
        }

        // Only the final active admin is protected; while colleagues remain the
        // change is reversible by another admin.
        return User::where('role', 'admin')->where('is_active', true)->whereKeyNot($target->id)->exists();
    }
}
