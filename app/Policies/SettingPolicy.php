<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}