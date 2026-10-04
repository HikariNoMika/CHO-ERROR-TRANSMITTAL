<?php

namespace App\Services;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\DocumentGeneration;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class DocumentGenerationService
{
    protected TemplateParserService $templateParser;
    protected XlsxDirectGenerationService $directService;

    public function __construct(
        TemplateParserService $templateParser,
        XlsxDirectGenerationService $directService
    ) {
        $this->templateParser = $templateParser;
        $this->directService = $directService;
    }

    public function generate(PatientRecord $record, User $user): DocumentGeneration
    {
        $template = $record->template;

        // Load template
        $templatePath = Storage::disk('private')->path($template->file_path);

        // Textbox-based templates (background picture + floating text boxes)
        // cannot survive a PhpSpreadsheet round-trip, so edit the XLSX directly.
        $placeholders = $this->templateParser->parse($templatePath);
        $hasTextboxes = collect($placeholders)->contains(fn ($p) => ($p['location'] ?? 'cell') === 'textbox');

        if ($hasTextboxes) {
            $result = $this->directService->generate($record, $templatePath);
            $generatedPath = $result['path'];
            foreach ($result['warnings'] as $warning) {
                session()->flash('generation_warnings', array_merge(session('generation_warnings', []), [$warning]));
            }
        } else {
            $spreadsheet = IOFactory::load($templatePath);

            // Replace text placeholders
            $this->replaceTextPlaceholders($spreadsheet, $record);

            // Insert images
            $this->insertImages($spreadsheet, $record);

            // Save generated file
            $generatedPath = $this->saveGeneratedFile($spreadsheet, $record, $template);
        }

        // Create generation record
        $generation = DocumentGeneration::create([
            'patient_record_id' => $record->id,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'file_path' => $generatedPath,
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);

        // Update patient record
        $record->update([
            'generated_file_path' => $generatedPath,
            'status' => 'generated',
            'date_today' => now()->toDateString(),
        ]);

        return $generation;
    }

    protected function replaceTextPlaceholders(Spreadsheet $spreadsheet, PatientRecord $record): void
    {
        $data = $this->getReplacementData($record);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cell = $sheet->getCell([$col, $row]);
                    $value = $cell->getValue();

                    if (is_string($value)) {
                        $newValue = $this->replacePlaceholdersInText($value, $data);
                        if ($newValue !== $value) {
                            // Preserve cell style
                            $style = $cell->getStyle();
                            $cell->setValue($newValue);
                            // Reapply style to maintain formatting
                            $cell->setStyle($style);

                            // Let Excel shrink the font itself if the value no
                            // longer fits the column. The template keeps its own
                            // font size unless the text really is too wide.
                            if ($this->shouldAutofitField($value)) {
                                $alignment = $cell->getAlignment();
                                $alignment->setWrapText(false);
                                $alignment->setShrinkToFit(true);
                                $cell->setAlignment($alignment);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function shouldAutofitField(string $rawCellText): bool
    {
        $config = config('mca.autofit');
        if (!($config['enabled'] ?? true)) {
            return false;
        }
        $fields = $config['fields'];
        if (in_array('*', $fields, true)) {
            return true;
        }
        preg_match_all('/\{\{(\w+)\}\}/', $rawCellText, $matches);

        foreach ($matches[1] as $name) {
            $canonical = PlaceholderMap::canonicalText($name) ?? $name;
            if (in_array($canonical, $fields, true)) {
                return true;
            }
        }

        return false;
    }

    protected function replacePlaceholdersInText(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? $matches[0];
        }, $text);
    }

    protected function getReplacementData(PatientRecord $record): array
    {
        $canonical = [
            'patient_name' => $record->patient_name,
            'birthdate' => $record->birthdate?->format('m-d-y') ?? '',
            'date_today' => ($record->date_today ?? now())->format('m-d-y'),
            'philhealth_id' => $record->philhealth_id,
            'head_of_clinic' => $record->head_of_clinic,
            'facility_name' => \App\Models\Setting::getFacilityName(),
            'facility_address' => \App\Models\Setting::getFacilityAddress(),
            'appointment_date' => $record->appointment_date?->format('m-d-y') ?? '',
            'auth_transaction_code' => (string) ($record->auth_transaction_code ?? ''),
            'pcu_error_code' => (string) ($record->pcu_error_code ?? ''),
        ];
        // Expose every known alias so templates using a different
        // vocabulary (e.g. {{person_fullname}}) resolve too.
        $data = $canonical;
        foreach (PlaceholderMap::TEXT as $alias => $field) {
            $data[$alias] = $canonical[$field] ?? '';
        }
        $data['fullname'] = $canonical['patient_name'];
        return $data;
    }

    protected function insertImages(Spreadsheet $spreadsheet, PatientRecord $record): void
    {
        $imagePlaceholders = [
            'image_with_id' => $record->image_with_id_path,
            'person_with_id' => $record->image_with_id_path,
            'empanelment_error' => $record->empanelment_error_image_path,
        ];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($imagePlaceholders as $placeholder => $imagePath) {
                $this->insertImageInSheet($sheet, $placeholder, $imagePath);
            }
        }
    }

    protected function insertImageInSheet(Worksheet $sheet, string $placeholder, ?string $imagePath): void
    {
        if (!$imagePath || !Storage::disk('private')->exists($imagePath)) {
            return;
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = $cell->getValue();

                if (is_string($value) && str_contains($value, "{{$placeholder}}")) {
                    $this->insertImageAtCell($sheet, $cell, $imagePath);
                    // Clear the placeholder text after inserting image
                    $cell->setValue(str_replace("{{$placeholder}}", '', $value));
                    return;
                }
            }
        }
    }

    protected function insertImageAtCell(Worksheet $sheet, \PhpOffice\PhpSpreadsheet\Cell\Cell $cell, string $imagePath): void
    {
        $coordinate = $cell->getCoordinate();
        $fullPath = Storage::disk('private')->path($imagePath);

        $drawing = new Drawing();
        $drawing->setName('Image');
        $drawing->setDescription('Patient Image');
        $drawing->setPath($fullPath);
        $drawing->setCoordinates($coordinate);
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);
        $drawing->setWorksheet($sheet);

        // Maintain aspect ratio, fit within reasonable bounds
        $imageSize = getimagesize($fullPath);
        if ($imageSize) {
            $width = $imageSize[0];
            $height = $imageSize[1];
            
            $maxWidth = 300;
            $maxHeight = 200;
            
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $drawing->setWidth((int)($width * $ratio));
            $drawing->setHeight((int)($height * $ratio));
        }
    }

    protected function saveGeneratedFile(Spreadsheet $spreadsheet, PatientRecord $record, Template $template): string
    {
        $sanitizedName = Str::slug($record->patient_name);
        $dateStr = now()->format('Y-m-d');
        $uniqueId = Str::upper(Str::random(6));
        $filename = "EMPANELMENT_{$sanitizedName}_{$dateStr}_{$uniqueId}.xlsx";

        $path = "generated-documents/" . now()->format('Y/m');
        $fullPath = storage_path("app/private/{$path}");

        Storage::disk('private')->makeDirectory($path);
        
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save(storage_path("app/private/{$path}/{$filename}"));

        return "{$path}/{$filename}";
    }
}