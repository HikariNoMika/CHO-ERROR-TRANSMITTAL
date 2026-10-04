<?php

namespace App\Http\Controllers;

use App\Models\DocumentGeneration;
use App\Models\PatientRecord;
use App\Services\AuditLogService;
use App\Services\DocumentGenerationService;
use App\Services\PlaceholderMap;
use App\Services\XlsxTemplatePreviewService;
use App\Support\RecordType;
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

        if (! $template) {
            return [];
        }

        $requiredFields = $template->fields()->where('is_required', true)->pluck('placeholder')->toArray();

        $missingFields = [];
        foreach ($requiredFields as $field) {
            $slot = PlaceholderMap::imageSlot($field);
            $canonical = PlaceholderMap::canonicalText($field);

            if ($slot !== null) {
                // Each type decides which slots it can actually supply. A
                // mission record, for example, has no PCU error screenshot to
                // give even if a template carries the placeholder.
                if (! $this->slotApplies($slot, $record->record_type)) {
                    continue;
                }

                $column = PlaceholderMap::slotColumn($slot);
                if ($column && empty($record->$column)) {
                    $missingFields[] = PlaceholderMap::slotLabel($slot);
                }

                continue;
            }

            if ($canonical !== null && in_array($canonical, PlaceholderMap::REQUIRED_TEXT_FIELDS, true) && empty($record->$canonical)) {
                $missingFields[] = ucfirst(str_replace('_', ' ', $canonical));
            }
        }

        return array_values(array_unique($missingFields));
    }

    /** Whether this record type is expected to supply the given image slot. */
    protected function slotApplies(string $slot, ?string $recordType): bool
    {
        return match ($slot) {
            'error' => RecordType::needsErrorImage($recordType),
            'id_proof' => RecordType::needsIdProof($recordType),
            default => RecordType::needsEvidence($recordType),
        };
    }

    public function generate(PatientRecord $record)
    {
        Gate::authorize('generate', $record);

        // A printing record with no layout cannot produce anything. Say so
        // plainly instead of failing deeper in the generator.
        if (! $record->template) {
            return back()->withErrors([
                'generation' => 'This record has no template. Upload a '
                    .RecordType::label($record->record_type)
                    .' template in Settings, then save the record again.',
            ]);
        }

        $missingFields = $this->missingRequiredFields($record);

        if (! empty($missingFields)) {
            return back()->withErrors([
                'generation' => 'Missing required fields: '.implode(', ', $missingFields),
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
                'generation' => 'Document generation failed. Please check the template and required fields.',
            ]);
        }
    }

    public function printView(PatientRecord $record, XlsxTemplatePreviewService $previewService)
    {
        Gate::authorize('print', $record);

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

        if (! $record->generated_file_path || ! Storage::disk('private')->exists($record->generated_file_path)) {
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

        if (! $generation->file_path || ! Storage::disk('private')->exists($generation->file_path)) {
            abort(404, 'Generated document not found.');
        }

        $this->auditLog->logDocumentDownloaded($generation->patientRecord, request());

        $path = Storage::disk('private')->path($generation->file_path);
        $filename = "EMPANELMENT_{$generation->patientRecord->patient_name}_{$generation->generated_at->format('Y-m-d')}.xlsx";

        return response()->download($path, $filename);
    }

    public function markAsPrinted(PatientRecord $record)
    {
        Gate::authorize('markPrinted', $record);

        $record->update(['status' => 'printed']);

        $this->auditLog->logDocumentPrinted($record, request());

        return back()->with('success', 'Record marked as printed.');
    }
}
