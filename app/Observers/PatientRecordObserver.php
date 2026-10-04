<?php

namespace App\Observers;

use App\Models\PatientRecord;

class PatientRecordObserver
{
    public function created(PatientRecord $patientRecord): void
    {
        // Could trigger notifications or other events
    }

    public function updated(PatientRecord $patientRecord): void
    {
        // If status changed to generated, could trigger print queue
    }

    public function deleted(PatientRecord $patientRecord): void
    {
        // Soft delete - files remain
    }

    public function forceDeleted(PatientRecord $patientRecord): void
    {
        // Hard delete - clean up files
        if ($patientRecord->image_with_id_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($patientRecord->image_with_id_path);
        }
        if ($patientRecord->empanelment_error_image_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($patientRecord->empanelment_error_image_path);
        }
        if ($patientRecord->generated_file_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($patientRecord->generated_file_path);
        }
        
        // Also delete generation files
        foreach ($patientRecord->documentGenerations as $generation) {
            if ($generation->file_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($generation->file_path);
            }
        }
    }
}