<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRecordRequest;
use App\Models\PatientRecord;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientRecordController extends Controller
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

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Patient Records');

        // Title + covered date range.
        $period = 'All dates';
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->filled('date_from')
                ? \Carbon\Carbon::parse($request->date_from)->format('m-d-y')
                : 'start';
            $to = $request->filled('date_to')
                ? \Carbon\Carbon::parse($request->date_to)->format('m-d-y')
                : 'today';
            $period = "{$from} to {$to}";
        }
        $sheet->setCellValue('A1', 'MCA Patient Records Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "Period: {$period}");
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');

        $headers = [
            'Patient Name', 'Birthdate', 'PhilHealth ID',
            'PCU Error Code', 'Created By', 'Created At',
        ];
        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:F3')->getFont()->setBold(true);
        $sheet->getStyle('A3:F3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');
        $sheet->freezePane('A4');

        // ID-like columns must stay text, or Excel coerces long digit
        // strings into numbers (e.g. 172008370449 -> 1.72E+11).
        $sheet->getStyle('C:D')->getNumberFormat()->setFormatCode('@');

        $row = 4;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->patient_name,
                $record->birthdate?->format('m-d-y') ?? '',
                null, // C written explicitly below as text
                null, // D written explicitly below as text
                $record->creator?->name ?? '',
                $record->created_at?->format('m-d-y g:i A') ?? '',
            ], null, "A{$row}");
            $sheet->setCellValueExplicit(
                "C{$row}",
                (string) $record->philhealth_id,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValueExplicit(
                "D{$row}",
                (string) ($record->pcu_error_code ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->auditLog->log(
            'exported_report',
            "Exported patient records report ({$records->count()} rows)",
            PatientRecord::class,
            null,
            $request
        );

        $filename = 'MCA_Records_' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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

        // Paginated separately: the history table can grow without bound and
        // the record header should not wait on every generation row.
        $generations = $record->documentGenerations()
            ->with('generator')
            ->latest('generated_at')
            ->paginate(10);

        return view('patient-records.show', compact('record', 'generations'));
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
        } elseif ($request->filled('remove_image_with_id')) {
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
        } elseif ($request->filled('remove_empanelment_error_image')) {
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