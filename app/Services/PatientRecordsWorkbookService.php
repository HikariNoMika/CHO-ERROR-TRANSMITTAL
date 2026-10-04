<?php

namespace App\Services;

use App\Models\PatientRecord;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the tabular patient-records workbook used by both exports: the whole
 * filtered list, and just the rows ticked on the page. Sharing one builder keeps
 * the two downloads identical apart from which records they cover.
 */
class PatientRecordsWorkbookService
{
    /** Columns that must stay text or Excel mangles long digit strings into numbers. */
    protected const TEXT_COLUMNS = ['C', 'D'];

    public function build(iterable $records, string $period, string $title = 'MCA Patient Records Report'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Patient Records');

        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "Period: {$period}");
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');

        $sheet->fromArray([
            'Patient Name', 'Birthdate', 'PhilHealth ID',
            'PCU Error Code', 'Created By', 'Created At',
        ], null, 'A3');
        $sheet->getStyle('A3:F3')->getFont()->setBold(true);
        $sheet->getStyle('A3:F3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');
        $sheet->freezePane('A4');
        $sheet->getStyle('C:D')->getNumberFormat()->setFormatCode('@');

        $row = 4;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->patient_name,
                $record->birthdate?->format('m-d-y') ?? '',
                null, // written explicitly below so it stays text
                null,
                $record->creator?->name ?? '',
                $record->created_at?->format('m-d-y g:i A') ?? '',
            ], null, "A{$row}");
            $sheet->setCellValueExplicit("C{$row}", (string) $record->philhealth_id, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", (string) ($record->pcu_error_code ?? ''), DataType::TYPE_STRING);
            $row++;
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function stream(Spreadsheet $spreadsheet, string $filename)
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Reads the ticked ids, keeping the order they were ticked in. */
    public function recordsInSelectionOrder(array $ids)
    {
        $found = PatientRecord::with('creator')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if ($found->has($id)) {
                $ordered[] = $found->get($id);
            }
        }

        return $ordered;
    }
}