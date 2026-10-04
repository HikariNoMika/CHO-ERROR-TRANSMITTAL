<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;
use DOMDocument;
use DOMXPath;

class TemplateParserService
{
    public function parse(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $placeholders = [];

        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
            $sheetPlaceholders = $this->extractPlaceholdersFromSheet($sheet, $sheetIndex);
            $placeholders = array_merge($placeholders, $sheetPlaceholders);
        }

        // Floating text boxes are invisible to the cell scan above, so scan
        // the drawing XML directly. Templates built on a background picture
        // keep all of their placeholders there.
        $placeholders = array_merge($placeholders, $this->extractPlaceholdersFromTextboxes($filePath));

        return $placeholders;
    }

    public function extractPlaceholdersFromSheet(Worksheet $sheet, int $sheetIndex): array
    {
        $placeholders = [];
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = $cell->getValue();

                if (is_string($value)) {
                    $matches = $this->findPlaceholders($value);
                    foreach ($matches as $match) {
                        $placeholders[] = [
                            'placeholder' => $match,
                            'cell' => $cell->getCoordinate(),
                            'sheet_index' => $sheetIndex,
                            'sheet_name' => $sheet->getTitle(),
                            'original_value' => $value,
                            'location' => 'cell',
                            'type' => $this->determinePlaceholderType($match),
                        ];
                    }
                }
            }
        }

        return $placeholders;
    }

    /**
     * Scan drawing XML (xl/drawings/drawing*.xml) for text-box placeholders.
     */
    public function extractPlaceholdersFromTextboxes(string $filePath): array
    {
        $placeholders = [];
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $placeholders;
        }

        $drawingFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#xl/drawings/drawing\d+\.xml$#', $name)) {
                $drawingFiles[] = $name;
            }
        }

        foreach ($drawingFiles as $drawingFile) {
            $xml = $zip->getFromName($drawingFile);
            if ($xml === false) {
                continue;
            }
            $dom = new DOMDocument();
            if (!@$dom->loadXML($xml)) {
                continue;
            }
            $xp = new DOMXPath($dom);
            $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $xp->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');

            foreach ($xp->query('//xdr:sp | //xdr:cxnSp') as $shape) {
                $nameNodes = $xp->query('.//xdr:cNvPr', $shape);
                $shapeName = $nameNodes->length ? $nameNodes->item(0)->getAttribute('name') : '?';
                foreach ($xp->query('.//a:p', $shape) as $para) {
                    $text = '';
                    foreach ($xp->query('.//a:t', $para) as $t) {
                        $text .= $t->nodeValue;
                    }
                    foreach ($this->findPlaceholders($text) as $match) {
                        $placeholders[] = [
                            'placeholder' => $match,
                            'cell' => $shapeName,
                            'sheet_index' => null,
                            'sheet_name' => $this->sheetNameForDrawing($zip, $drawingFile) ?? basename($drawingFile),
                            'original_value' => $text,
                            'location' => 'textbox',
                            'type' => $this->determinePlaceholderType($match),
                        ];
                    }
                }
            }
        }

        $zip->close();
        return $placeholders;
    }

    /**
     * Resolve which worksheet a drawing file belongs to via sheet rels.
     */
    protected function sheetNameForDrawing(ZipArchive $zip, string $drawingFile): ?string
    {
        $drawingBase = basename($drawingFile);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('#xl/worksheets/_rels/(sheet\d+)\.xml\.rels$#', $name, $m)) {
                continue;
            }
            $rels = $zip->getFromIndex($i);
            if ($rels !== false && strpos($rels, $drawingBase) !== false) {
                return $this->sheetTitle($zip, $m[1]);
            }
        }
        return null;
    }

    protected function sheetTitle(ZipArchive $zip, string $sheetBase): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if ($workbook === false) {
            return null;
        }
        $dom = new DOMDocument();
        if (!@$dom->loadXML($workbook)) {
            return null;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $xp->query('//m:sheets/m:sheet');
        $index = (int) filter_var($sheetBase, FILTER_SANITIZE_NUMBER_INT);
        // sheetN usually matches the Nth sheet element, but fall back to r:id matching
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels !== false) {
            $rdom = new DOMDocument();
            if (@$rdom->loadXML($rels)) {
                $rxp = new DOMXPath($rdom);
                $rxp->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
                foreach ($rxp->query('//r:Relationship') as $rel) {
                    if (basename($rel->getAttribute('Target')) === $sheetBase . '.xml') {
                        $rid = $rel->getAttribute('Id');
                        foreach ($sheets as $s) {
                            if ($s->getAttribute('r:id') === $rid || $s->getAttribute('id') === $rid) {
                                return $s->getAttribute('name');
                            }
                        }
                    }
                }
            }
        }
        if ($sheets->length >= $index) {
            return $sheets->item($index - 1)->getAttribute('name');
        }
        return null;
    }

    public function findPlaceholders(string $text): array
    {
        $pattern = '/\{\{(\w+)\}\}/';
        preg_match_all($pattern, $text, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    public function determinePlaceholderType(string $placeholder): string
    {
        return PlaceholderMap::type($placeholder);
    }

    public function getTemplateInfo(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[] = [
                'name' => $sheet->getTitle(),
                'index' => $sheet->getParent()->getIndex($sheet),
            ];
        }

        return [
            'sheet_count' => count($sheets),
            'sheets' => $sheets,
        ];
    }
}
