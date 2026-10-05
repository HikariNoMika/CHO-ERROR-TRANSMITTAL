<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function log(
        string $action,
        ?string $description = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?Request $request = null
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'user_agent' => $request?->userAgent() ?? request()->userAgent(),
        ]);
    }

    public function logRecordCreated(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'created_record',
            "Created patient record for {$record->patient_name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logRecordUpdated(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'updated_record',
            "Updated patient record for {$record->patient_name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logDocumentGenerated(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'generated_document',
            "Generated document for {$record->patient_name} using template {$record->template->name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logDocumentDownloaded(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'downloaded_document',
            "Downloaded generated document for {$record->patient_name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logDocumentPrinted(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'printed_document',
            "Opened print view for {$record->patient_name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logRecordDeleted(PatientRecord $record, ?Request $request = null): AuditLog
    {
        return $this->log(
            'deleted_record',
            "Deleted patient record for {$record->patient_name}",
            PatientRecord::class,
            $record->id,
            $request
        );
    }

    public function logTemplateUploaded(Template $template, ?Request $request = null): AuditLog
    {
        return $this->log(
            'uploaded_template',
            "Uploaded template: {$template->name} v{$template->version}",
            Template::class,
            $template->id,
            $request
        );
    }

    public function logTemplateUpdated(Template $template, ?Request $request = null): AuditLog
    {
        return $this->log(
            'updated_template',
            "Updated template: {$template->name}",
            Template::class,
            $template->id,
            $request
        );
    }

    /**
     * A user changing their own name, email or password.
     *
     * Takes the acting user explicitly rather than reading Auth::user(), so a
     * caller can record an edit to somebody else's profile.
     */
    public function logProfileUpdated(User $user, string $description, ?Request $request = null): AuditLog
    {
        return $this->log(
            'updated_profile',
            $description,
            User::class,
            $user->id,
            $request
        );
    }
}
