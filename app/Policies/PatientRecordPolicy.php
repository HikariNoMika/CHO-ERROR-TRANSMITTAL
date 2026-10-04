<?php

namespace App\Policies;

use App\Models\PatientRecord;
use App\Models\User;

class PatientRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, PatientRecord $record): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && ($user->isStaff() || $user->isAdmin());
    }

    public function update(User $user, PatientRecord $record): bool
    {
        if (!$user->is_active) {
            return false;
        }
        
        // Admin can update any record
        if ($user->isAdmin()) {
            return true;
        }
        
        // Staff can update their own records
        return $record->created_by === $user->id;
    }

    public function delete(User $user, PatientRecord $record): bool
    {
        if (!$user->is_active) {
            return false;
        }
        
        // Only admin can delete
        return $user->isAdmin();
    }

    public function generate(User $user, PatientRecord $record): bool
    {
        return $user->is_active && ($user->isStaff() || $user->isAdmin());
    }

    public function download(User $user, PatientRecord $record): bool
    {
        return $user->is_active;
    }
}