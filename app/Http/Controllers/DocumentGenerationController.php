<?php

namespace App\Http\Controllers;

use App\Models\PatientRecord;
use App\Models\DocumentGeneration;
use App\Services\DocumentGenerationService;
use App\Services\PlaceholderMap;
use App\Services\XlsxTemplatePreviewService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentGenerationController extends Controller
{
    protected DocumentGenerationService $documentService;
    protected AuditLogService $auditLog;

    public function __construct(
        DocumentGenerationService $documentService,
        AuditLogService $auditLog
    ) {
        $this->documentService = $documentService;
        $this->auditLog = $auditLog;
    }

    /**
     * Required-field check for generating a document from a record.
     *
     * Shared by the single-record and bulk paths so the two can never drift:
     * if a new required placeholder is added here, bulk generation inherits it.
     *
     * @return array<int, string> Human-readable list of what is missing.
     */
    protected function missingRequiredFields(PatientRecord $record): array
    {
        $template = $record->template;
        $requiredFields = $template->fields()->where('is_required', true)->pluck('placeholder')->toArray();

        $isSuccess = ($record->record_type ?? 'error') === 'success';
        $missingFields = [];
        foreach ($requiredFields as $field) {
            $slot = PlaceholderMap::imageSlot($field);
            $canonical = PlaceholderMap::canonicalText($field);
            if ($isSuccess && $slot !== null) {
                continue; // success quick-logs print with whatever images are attached
            }
            if ($slot === 'id' && !$record->image_with_id_path) {
                $missingFields[] = 'ID Image';
            } elseif ($slot === 'error' && !$record->empanelment_error_image_path) {
                $missingFields[] = 'Empanelment Error Image';
            } elseif ($canonical !== null && in_array($canonical, PlaceholderMap::REQUIRED_TEXT_FIELDS, true) && empty($record->$canonical)) {
                $missingFields[] = ucfirst(str_replace('_', ' ', $canonical));
            }
        }

        return array_values(array_unique($missingFields));
    }

    public function generate(PatientRecord $record)
    {
        Gate::authorize('generate', $record);

        $missingFields = $this->missingRequiredFields($record);

        if (!empty($missingFields)) {
            return back()->withErrors([
                'generation' => 'Missing required fields: ' . implode(', ', $missingFields)
            ]);
        }

        try {
            $generation = $this->documentService->generate($record, request()->user());
            
            $this->auditLog->logDocumentGenerated($record, request());

            return redirect()->route('records.print', ['record' => $record->id, 'print' => 1])
                ->with('success', 'Document generated successfully.')
                ->with('generation_id', $generation->id);

        } catch (\Exception $e) {
            \Log::error('Document generation failed', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'generation' => 'Document generation failed. Please check the template and required fields.'
            ]);
        }
    }

    /**
     * Generate documents for several records in one submit.
     *
     * POST rather than GET on purpose: the single-record route is a GET that
     * writes files and flips status, and repeating that N times from a bulk
     * action would make it trivially triggerable by a link or a prefetch.
     */
    public function bulkGenerate(Request $request)
    {
        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*' => ['integer', 'distinct'],
        ]);

        $ids = $validated['records'];

        // Fetch exactly what was asked for. A count mismatch means someone
        // tampered with the id list, so fail loudly instead of generating a
        // partial set.
        $records = PatientRecord::whereIn('id', $ids)->get();
        if ($records->count() !== count($ids)) {
            return back()->withErrors([
                'generation' => 'One or more selected records no longer exist. Refresh and try again.',
            ]);
        }

        // Authorise every record up front. Silently skipping the ones the user
        // may not generate would hide a permission problem, so reject instead.
        foreach ($records as $record) {
            Gate::authorize('generate', $record);
        }

        $generated = [];
        $failed = [];

        foreach ($records as $record) {
            // No status gate here, deliberately. The single-record route has
            // never blocked re-generating a finished record, so bulk behaves the
            // same way; the index only exposes non-drafts once a date filter is
            // applied, which keeps that a deliberate user choice.
            $missing = $this->missingRequiredFields($record);
            if (!empty($missing)) {
                $failed[] = $record->patient_name . ' — missing ' . implode(', ', $missing);
                continue;
            }

            try {
                $this->documentService->generate($record, $request->user());
                $this->auditLog->logDocumentGenerated($record, $request);
                $generated[] = $record->patient_name;
            } catch (\Throwable $e) {
                // One bad record must not abandon the rest of the batch.
                \Log::error('Bulk document generation failed', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $record->patient_name . ' — ' . $e->getMessage();
            }
        }

        return back()->with('bulk_result', [
            'generated' => $generated,
            'failed' => $failed,
        ]);
    }

    public function printView(PatientRecord $record, XlsxTemplatePreviewService $previewService)
    {
        Gate::authorize('view', $record);

        $record->load(['template', 'creator']);
        $generationId = session('generation_id');

        // Template-faithful preview when the layout lives in text boxes;
        // null for cell-based templates, which keep the generic print view.
        try {
            $preview = $previewService->render($record);
        } catch (\Throwable $e) {
            \Log::warning('Template preview failed, using generic print view.', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            $preview = null;
        }

        return view('patient-records.print', compact('record', 'generationId', 'preview'));
    }

    public function download(PatientRecord $record)
    {
        Gate::authorize('download', $record);

        if (!$record->generated_file_path || !Storage::disk('private')->exists($record->generated_file_path)) {
            abort(404, 'Generated document not found.');
        }

        $this->auditLog->logDocumentDownloaded($record, request());

        $path = Storage::disk('private')->path($record->generated_file_path);
        $filename = "EMPANELMENT_{$record->patient_name}_{$record->created_at->format('Y-m-d')}.xlsx";

        return response()->download($path, $filename);
    }

    public function downloadGeneration(DocumentGeneration $generation)
    {
        Gate::authorize('download', $generation->patientRecord);

        if (!$generation->file_path || !Storage::disk('private')->exists($generation->file_path)) {
            abort(404, 'Generated document not found.');
        }

        $this->auditLog->logDocumentDownloaded($generation->patientRecord, request());

        $path = Storage::disk('private')->path($generation->file_path);
        $filename = "EMPANELMENT_{$generation->patientRecord->patient_name}_{$generation->generated_at->format('Y-m-d')}.xlsx";

        return response()->download($path, $filename);
    }

    public function markAsPrinted(PatientRecord $record)
    {
        Gate::authorize('update', $record);

        $record->update(['status' => 'printed']);
        
        $this->auditLog->logDocumentPrinted($record, request());

        return back()->with('success', 'Record marked as printed.');
    }
}