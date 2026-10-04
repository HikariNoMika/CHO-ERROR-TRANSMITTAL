<?php

namespace App\Policies;

use App\Models\PatientRecord;
use App\Models\User;
use App\Support\RecordType;

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
        if (! $user->is_active) {
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
        if (! $user->is_active) {
            return false;
        }

        // Only admin can delete
        return $user->isAdmin();
    }

    public function generate(User $user, PatientRecord $record): bool
    {
        // Data-only records have no document to produce.
        return $user->is_active
            && RecordType::usesTemplate($record->record_type)
            && ($user->isStaff() || $user->isAdmin());
    }

    public function download(User $user, PatientRecord $record): bool
    {
        return $user->is_active
            && RecordType::usesTemplate($record->record_type);
    }

    /**
     * Opening the print preview is a printing action, not a viewing one: a
     * data-only record has nothing to lay out, so the preview must stay closed.
     */
    public function print(User $user, PatientRecord $record): bool
    {
        return $this->download($user, $record);
    }

    /**
     * "Mark as printed" records that paper left the building, which only a
     * record with a document can have.
     */
    public function markPrinted(User $user, PatientRecord $record): bool
    {
        return $this->generate($user, $record);
    }
}
