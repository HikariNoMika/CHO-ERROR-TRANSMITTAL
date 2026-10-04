<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRecordRequest;
use App\Models\PatientRecord;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use App\Services\AuditLogService;
use App\Services\PatientRecordsWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientRecordController extends Controller
{
    protected DocumentGenerationService $documentService;
    protected AuditLogService $auditLog;
    protected PatientRecordsWorkbookService $workbook;

    public function __construct(
        DocumentGenerationService $documentService,
        AuditLogService $auditLog,
        PatientRecordsWorkbookService $workbook
    ) {
        $this->documentService = $documentService;
        $this->auditLog = $auditLog;
        $this->workbook = $workbook;
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', PatientRecord::class);

        // Direct links (/records/error, /records/success) carry the type as
        // a route default; normalise it into the request so every downstream
        // reader (title, tabs, filters, export) works unchanged.
        if ($request->route('type') && !$request->filled('type')) {
            $request->merge(['type' => $request->route('type')]);
        }

        // Page size is user-selectable; clamp to the offered options so a
        // hand-edited query string cannot ask for an unbounded result set.
        $perPage = (int) $request->query('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $records = $this->filteredQuery($request)->paginate($perPage)->withQueryString();

        return view('patient-records.index', compact('records', 'perPage'));
    }

    /**
     * Download the currently filtered records as an Excel report.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', PatientRecord::class);

        $records = $this->filteredQuery($request)->limit(5000)->get();

        $spreadsheet = $this->workbook->build($records, $this->dateRangeLabel($request));

        $this->auditLog->log(
            'exported_report',
            "Exported patient records report ({$records->count()} rows)",
            PatientRecord::class,
            null,
            $request
        );

        $filename = 'MCA_Records_' . now()->format('Y-m-d_His') . '.xlsx';

        return $this->workbook->stream($spreadsheet, $filename);
    }

    /**
     * Downloads just the rows ticked on the page, as one workbook.
     *
     * Read-only on purpose: no document is generated, no file is stored and no
     * status changes, so this is safe to run on records that are already printed.
     */
    public function bulkExport(Request $request)
    {
        Gate::authorize('viewAny', PatientRecord::class);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:5000'],
            'records.*' => ['integer', 'distinct'],
        ]);

        $records = $this->workbook->recordsInSelectionOrder($validated['records']);

        if ($records === []) {
            return back()->withErrors([
                'export' => 'None of the selected records could be found. Refresh and try again.',
            ]);
        }

        $count = count($records);
        $scope = count($validated['records']) === $count
            ? "{$count} selected record" . ($count === 1 ? '' : 's')
            : "{$count} of " . count($validated['records']) . ' selected records found';

        // The rows were ticked off a filtered list, so the sheet states the range
        // they were chosen from as well as how many actually made it.
        $spreadsheet = $this->workbook->build(
            $records,
            $this->dateRangeLabel($request).' · '.$scope,
            'MCA Patient Records Export'
        );

        $filename = 'MCA_Records_Selected_' . now()->format('Y-m-d_His') . '.xlsx';

        return $this->workbook->stream($spreadsheet, $filename);
    }

    /** Shared search/filter logic for the list and the export. */
    protected function filteredQuery(Request $request)
    {
        $query = PatientRecord::with(['template', 'creator'])
            ->latest('created_at');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('philhealth_id', 'like', "%{$search}%")
                    ->orWhere('pcu_error_code', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        if ($request->filled('head_of_clinic')) {
            $query->where('head_of_clinic', 'like', "%{$request->head_of_clinic}%");
        }

        $type = $request->input('type', 'error');
        if (in_array($type, ['error', 'success'], true)) {
            $query->where('record_type', $type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * Human label for the date window the rows were filtered by, so every
     * download states the range it covers instead of leaving the reader to guess.
     */
    protected function dateRangeLabel(Request $request): string
    {
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            return 'All dates';
        }

        $from = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->date_from)->format('m-d-y')
            : 'start';
        $to = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->date_to)->format('m-d-y')
            : 'today';

        return "{$from} to {$to}";
    }

    public function create(Request $request)
    {
        Gate::authorize('create', PatientRecord::class);

        // Direct links (/records/error/create, /records/success/create) carry
        // the type as a route default; normalise it for the form preselect.
        if ($request->route('type') && !$request->filled('type')) {
            $request->merge(['type' => $request->route('type')]);
        }

        // Single-template mode: the first active template is the default.
        $defaultTemplate = Template::where('is_active', true)->orderBy('id')->first(['id', 'name']);

        return view('patient-records.create', compact('defaultTemplate'));
    }

    public function store(PatientRecordRequest $request)
    {
        Gate::authorize('create', PatientRecord::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';
        
        // Auto-fill head_of_clinic from settings if not provided
        if (empty($data['head_of_clinic'])) {
            $data['head_of_clinic'] = \App\Models\Setting::getHeadOfClinic();
        }

        // Handle image uploads
        if ($request->hasFile('image_with_id')) {
            $data['image_with_id_path'] = $request->file('image_with_id')->store('patient-images/' . now()->format('Y/m'), 'private');
        }

        if ($request->hasFile('empanelment_error_image')) {
            $data['empanelment_error_image_path'] = $request->file('empanelment_error_image')->store('patient-images/' . now()->format('Y/m'), 'private');
        }

        // Handle base64 pasted images
        if ($request->filled('image_with_id_base64')) {
            $data['image_with_id_path'] = $this->storeBase64Image($request->image_with_id_base64);
        }

        if ($request->filled('empanelment_error_image_base64')) {
            $data['empanelment_error_image_path'] = $this->storeBase64Image($request->empanelment_error_image_base64);
        }

        $record = PatientRecord::create($data);

        $this->auditLog->logRecordCreated($record, $request);

        // Single flow: every save generates the document and opens print.
        return redirect()->route('records.generate', $record);
    }

    public function show(PatientRecord $record)
    {
        Gate::authorize('view', $record);

        $record->load(['template', 'creator']);

        return view('patient-records.show', compact('record'));
    }

    public function edit(PatientRecord $record)
    {
        Gate::authorize('update', $record);

        return view('patient-records.edit', compact('record'));
    }

    public function update(PatientRecordRequest $request, PatientRecord $record)
    {
        Gate::authorize('update', $record);

        $data = $request->validated();

        // Handle image uploads
        if ($request->hasFile('image_with_id')) {
            // Delete old image
            if ($record->image_with_id_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->image_with_id_path);
            }
            $data['image_with_id_path'] = $request->file('image_with_id')->store('patient-images/' . now()->format('Y/m'), 'private');
        } elseif ($request->boolean('remove_image_with_id')) {
            if ($record->image_with_id_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->image_with_id_path);
            }
            $data['image_with_id_path'] = null;
        }

        if ($request->hasFile('empanelment_error_image')) {
            if ($record->empanelment_error_image_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->empanelment_error_image_path);
            }
            $data['empanelment_error_image_path'] = $request->file('empanelment_error_image')->store('patient-images/' . now()->format('Y/m'), 'private');
        } elseif ($request->boolean('remove_empanelment_error_image')) {
            if ($record->empanelment_error_image_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->empanelment_error_image_path);
            }
            $data['empanelment_error_image_path'] = null;
        }

        // Handle base64 pasted images
        if ($request->filled('image_with_id_base64')) {
            if ($record->image_with_id_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->image_with_id_path);
            }
            $data['image_with_id_path'] = $this->storeBase64Image($request->image_with_id_base64);
        }

        if ($request->filled('empanelment_error_image_base64')) {
            if ($record->empanelment_error_image_path) {
                \Illuminate\Support\Facades\Storage::disk('private')->delete($record->empanelment_error_image_path);
            }
            $data['empanelment_error_image_path'] = $this->storeBase64Image($request->empanelment_error_image_base64);
        }

        $record->update($data);

        $this->auditLog->logRecordUpdated($record, $request);

        // Single flow: every save regenerates the document and opens print.
        return redirect()->route('records.generate', $record);
    }

    public function image(PatientRecord $record, string $type)
    {
        Gate::authorize('view', $record);

        $path = match ($type) {
            'id' => $record->image_with_id_path,
            'error' => $record->empanelment_error_image_path,
            default => null,
        };

        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('private')->exists($path), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('private')->path($path));
    }

    public function destroy(PatientRecord $record)
    {
        Gate::authorize('delete', $record);

        // Delete associated files
        if ($record->image_with_id_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($record->image_with_id_path);
        }
        if ($record->empanelment_error_image_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($record->empanelment_error_image_path);
        }
        if ($record->generated_file_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($record->generated_file_path);
        }

        $this->auditLog->logRecordDeleted($record, request());

        $record->delete();

        return redirect()->route('records.error')
            ->with('success', 'Record deleted successfully.');
    }

    protected function storeBase64Image(string $base64): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        } else {
            $extension = 'png';
        }

        $content = base64_decode($base64);
        $filename = \Illuminate\Support\Str::uuid() . ".{$extension}";
        $path = "patient-images/" . now()->format('Y/m');
        $fullPath = storage_path("app/private/{$path}");

        \Illuminate\Support\Facades\Storage::disk('private')->makeDirectory($path);
        file_put_contents("{$fullPath}/{$filename}", $content);

        return "{$path}/{$filename}";
    }
}