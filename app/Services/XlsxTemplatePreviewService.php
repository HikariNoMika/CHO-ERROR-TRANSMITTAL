<?php

namespace App\Services;

use App\Models\PatientRecord;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Renders a template-faithful HTML preview of what the generated document
 * looks like: the template's background picture plus absolutely-positioned
 * boxes for every text-box placeholder (with live record values) and every
 * image placeholder (with the record's images).
 *
 * Returns null for cell-based templates, which keep the generic print view.
 */
class XlsxTemplatePreviewService
{
    const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function __construct(
        protected XlsxDirectGenerationService $direct,
        protected TextAutofitService $autofit
    ) {}

    /**
     * @return null|array{pages: array<int, array{background: ?string, bgX: float, bgY: float, bgW: float, bgH: float, width: float, height: float, boxes: array}>}
     */
    public function render(PatientRecord $record): ?array
    {
        $record->loadMissing('template');
        $absPath = Storage::disk('private')->path($record->template->file_path);

        $zip = new ZipArchive;
        if ($zip->open($absPath) !== true) {
            return null;
        }

        try {
            $drawings = $this->listFiles($zip, '#xl/drawings/drawing\d+\.xml$#');
            if (empty($drawings)) {
                return null;
            }
            // Each worksheet owns its own drawing part, so a drawing maps to one
            // printed page. Order them the way the workbook orders its sheets.
            $drawings = $this->orderByWorksheet($zip, $drawings);

            $values = $this->direct->resolveValues($record);
            $pages = [];
            $sawTextShapes = false;

            foreach ($drawings as $drawingFile) {
                $xml = $zip->getFromName($drawingFile);
                if ($xml === false) {
                    continue;
                }
                $dom = new DOMDocument;
                if (! @$dom->loadXML($xml)) {
                    continue;
                }
                $xp = new DOMXPath($dom);
                $xp->registerNamespace('a', self::A_NS);
                $xp->registerNamespace('xdr', self::DRAWING_NS);

                $boxes = [];
                $pics = [];
                $hasTextShapes = false;

                foreach ($xp->query('//xdr:twoCellAnchor | //xdr:oneCellAnchor') as $anchor) {
                    // Picture (e.g. the background image).
                    $pic = $xp->query('.//xdr:pic', $anchor);
                    if ($pic !== false && $pic->length > 0) {
                        $pics[] = $this->readPicture($zip, $xp, $drawingFile, $pic->item(0));

                        continue;
                    }
                    // Text shape.
                    $shape = null;
                    foreach (['.//xdr:sp', './/xdr:cxnSp'] as $q) {
                        $found = $xp->query($q, $anchor);
                        if ($found !== false && $found->length > 0) {
                            $shape = $found->item(0);
                            break;
                        }
                    }
                    if (! $shape) {
                        continue;
                    }
                    $hasTextShapes = true;
                    $box = $this->readTextShape($xp, $shape, $values, $record);
                    if ($box) {
                        $boxes[] = $box;
                    }
                }

                if ($hasTextShapes) {
                    $sawTextShapes = true;
                }

                // Largest picture = background. It keeps its own geometry:
                // stretching it to the canvas is what misaligned everything.
                $background = null;
                $maxArea = 0;
                foreach (array_filter($pics) as $pic) {
                    $area = $pic['w'] * $pic['h'];
                    if ($area > $maxArea) {
                        $maxArea = $area;
                        $background = $pic;
                    }
                }

                $width = 0;
                $height = 0;
                foreach ($boxes as $b) {
                    $width = max($width, $b['x'] + $b['w']);
                    $height = max($height, $b['y'] + $b['h']);
                }
                if ($background) {
                    $width = max($width, $background['x'] + $background['w']);
                    $height = max($height, $background['y'] + $background['h']);
                }
                if ($width <= 0 || $height <= 0) {
                    continue;
                }

                $pages[] = [
                    'background' => $background ? $background['dataUri'] : null,
                    'bgX' => $background['x'] ?? 0,
                    'bgY' => $background['y'] ?? 0,
                    'bgW' => $background['w'] ?? 0,
                    'bgH' => $background['h'] ?? 0,
                    'width' => $width,
                    'height' => $height,
                    'boxes' => $boxes,
                ];
            }

            if (! $sawTextShapes || $pages === []) {
                return null;
            }

            return ['pages' => $pages];
        } finally {
            $zip->close();
        }
    }

    /**
     * Sort drawing parts by the position of the worksheet that owns them, so a
     * multi-sheet workbook previews as page 1, page 2, ... in worksheet order.
     * Falls back to the drawing filename when a relationship is missing.
     *
     * @param  string[]  $drawings
     * @return string[]
     */
    protected function orderByWorksheet(ZipArchive $zip, array $drawings): array
    {
        $order = $this->drawingOrderMap($zip);

        usort($drawings, function ($a, $b) use ($order) {
            $oa = $order[basename($a)] ?? PHP_INT_MAX;
            $ob = $order[basename($b)] ?? PHP_INT_MAX;

            return $oa === $ob ? strcmp($a, $b) : $oa <=> $ob;
        });

        return $drawings;
    }

    /** @return array<string, int> drawing filename => 1-based worksheet position */
    protected function drawingOrderMap(ZipArchive $zip): array
    {
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $workbook = $zip->getFromName('xl/workbook.xml');
        $workbookRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $workbookRels === false) {
            return [];
        }

        $rdom = new DOMDocument;
        if (! @$rdom->loadXML($workbookRels)) {
            return [];
        }
        $rxp = new DOMXPath($rdom);
        $rxp->registerNamespace('r', self::REL_NS);
        $targets = [];
        foreach ($rxp->query('//r:Relationship') as $rel) {
            $targets[$rel->getAttribute('Id')] = basename($rel->getAttribute('Target'));
        }

        $wd = new DOMDocument;
        if (! @$wd->loadXML($workbook)) {
            return [];
        }
        $wx = new DOMXPath($wd);
        $wx->registerNamespace('m', $mainNs);
        $wx->registerNamespace('r', self::REL_NS);

        $map = [];
        $position = 0;
        foreach ($wx->query('//m:sheets/m:sheet') as $sheet) {
            $position++;
            $rid = $sheet->getAttributeNS(self::REL_NS, 'id');
            $sheetBase = $targets[$rid] ?? null;
            if (! $sheetBase) {
                continue;
            }
            $rels = $zip->getFromName('xl/worksheets/_rels/'.$sheetBase.'.rels');
            if ($rels === false) {
                continue;
            }
            if (preg_match('#drawings/(drawing\d+\.xml)#', $rels, $m)) {
                $map[$m[1]] = $position;
            }
        }

        return $map;
    }

    protected function readTextShape(DOMXPath $xp, $shape, array $values, PatientRecord $record): ?array
    {
        $geom = $this->shapeGeometry($xp, $shape);
        if (! $geom) {
            return null;
        }

        // Image placeholder?
        $fullText = '';
        foreach ($xp->query('.//a:t', $shape) as $t) {
            $fullText .= $t->nodeValue;
        }
        preg_match_all('/\{\{(\w+)\}\}/', $fullText, $m);
        foreach ($m[1] ?? [] as $ph) {
            $slot = PlaceholderMap::imageSlot($ph);
            if ($slot !== null) {
                return array_merge($geom, [
                    'kind' => 'image',
                    'src' => $this->imageDataUri($record, $slot),
                    'label' => PlaceholderMap::slotLabel($slot),
                ]);
            }
        }

        $lines = [];
        foreach ($xp->query('.//a:p', $shape) as $para) {
            $text = '';
            foreach ($xp->query('.//a:t', $para) as $t) {
                $text .= $t->nodeValue;
            }
            $text = preg_replace_callback('/\{\{(\w+)\}\}/', function ($mm) use ($values) {
                $canonical = PlaceholderMap::canonicalText($mm[1]);

                return $canonical !== null ? ($values[$canonical] ?? '') : '';
            }, $text);

            $pPr = $xp->query('./a:pPr', $para);
            $align = 'left';
            if ($pPr !== false && $pPr->length > 0) {
                $align = match ($pPr->item(0)->getAttribute('algn')) {
                    'ctr' => 'center',
                    'r' => 'right',
                    'just', 'distr' => 'justify',
                    default => 'left',
                };
            }
            $lines[] = [
                'text' => $text,
                'align' => $align,
                'style' => $this->runStyle($xp, $para),
            ];
        }

        $lines = $this->autofitLines($xp, $shape, $fullText, $lines);

        return array_merge($geom, ['kind' => 'text', 'lines' => $lines]);
    }

    protected function runStyle(DOMXPath $xp, $para): array
    {
        $style = ['size' => 11, 'bold' => false, 'color' => '000000'];
        $rPr = $xp->query('.//a:rPr', $para);
        if ($rPr === false || $rPr->length === 0) {
            return $style;
        }
        $node = $rPr->item(0);
        if ($node->hasAttribute('sz')) {
            $style['size'] = max(6, (int) $node->getAttribute('sz') / 100);
        }
        if ($node->getAttribute('b') === '1') {
            $style['bold'] = true;
        }
        $srgb = $xp->query('.//a:solidFill/a:srgbClr', $para);
        if ($srgb !== false && $srgb->length > 0 && $srgb->item(0)->hasAttribute('val')) {
            $style['color'] = $srgb->item(0)->getAttribute('val');
        }

        return $style;
    }

    /**
     * Applies the same shrink-to-fit the generator applies, so the preview and
     * the printed page match what Excel renders.
     *
     * The preview is built from the template, which carries no fontScale yet, so
     * the measurement has to be repeated here. Excel keeps a single scale per
     * shape, so the most constrained paragraph wins.
     */
    protected function autofitLines(DOMXPath $xp, $shape, string $rawText, array $lines): array
    {
        $config = config('mca.autofit');
        if (! ($config['enabled'] ?? true) || $lines === []) {
            return $lines;
        }

        if (! $this->shouldAutofitText($rawText, $config['fields'] ?? [])) {
            return $lines;
        }

        $ext = $xp->query('./xdr:spPr/a:xfrm/a:ext', $shape)->item(0);
        $bodyPr = $xp->query('./xdr:txBody/a:bodyPr', $shape)->item(0);
        if ($ext === null || $bodyPr === null) {
            return $lines;
        }

        $available = $this->autofit->availableWidthInPoints(
            (int) $ext->getAttribute('cx'),
            $bodyPr->hasAttribute('lIns') ? (int) $bodyPr->getAttribute('lIns') : TextAutofitService::DEFAULT_L_INS,
            $bodyPr->hasAttribute('rIns') ? (int) $bodyPr->getAttribute('rIns') : TextAutofitService::DEFAULT_R_INS
        ) * ($config['width_factor'] ?? 1.0);

        $scale = null;
        foreach ($lines as $line) {
            $candidate = $this->autofit->fontScaleFor(
                $line['text'],
                (float) $line['style']['size'],
                $available,
                (bool) $line['style']['bold'],
                (float) ($config['min_font_pt'] ?? 8.0),
                (float) ($config['tolerance'] ?? 1.02)
            );
            if ($candidate !== null && ($scale === null || $candidate < $scale)) {
                $scale = $candidate;
            }
        }

        if ($scale === null) {
            return $lines;
        }

        $factor = $scale / 100000;
        foreach ($lines as $i => $line) {
            $lines[$i]['style']['size'] = max(6, round($line['style']['size'] * $factor, 2));
        }

        return $lines;
    }

    /** Mirrors the generator's guard so preview and workbook autofit the same shapes. */
    protected function shouldAutofitText(string $rawText, array $fields): bool
    {
        if (in_array('*', $fields, true)) {
            return true;
        }
        if (! preg_match_all('/\{\{(\w+)\}\}/', $rawText, $m)) {
            return false;
        }
        foreach ($m[1] as $name) {
            $canonical = PlaceholderMap::canonicalText($name) ?? $name;
            if (in_array($canonical, $fields, true)) {
                return true;
            }
        }

        return false;
    }

    /** EMU geometry of a shape's xfrm → px at 96dpi. */
    protected function shapeGeometry(DOMXPath $xp, $shape): ?array
    {
        $xfrm = $xp->query('.//xdr:spPr/a:xfrm', $shape);
        if ($xfrm === false || $xfrm->length === 0) {
            return null;
        }
        $off = $xp->query('./a:off', $xfrm->item(0));
        $ext = $xp->query('./a:ext', $xfrm->item(0));
        if ($off === false || $off->length === 0 || $ext === false || $ext->length === 0) {
            return null;
        }
        $px = fn ($emu) => round(((int) $emu) / 9525, 1);

        return [
            'x' => $px($off->item(0)->getAttribute('x')),
            'y' => $px($off->item(0)->getAttribute('y')),
            'w' => $px($ext->item(0)->getAttribute('cx')),
            'h' => $px($ext->item(0)->getAttribute('cy')),
        ];
    }

    protected function readPicture(ZipArchive $zip, DOMXPath $xp, string $drawingFile, $pic): ?array
    {
        $blip = $xp->query('.//a:blip', $pic);
        if ($blip === false || $blip->length === 0) {
            return null;
        }
        $embed = $blip->item(0)->getAttribute('embed') ?: $blip->item(0)->getAttribute('r:embed');
        if (! $embed) {
            // Namespaced lookup fallback.
            $blip2 = $xp->query('.//*[local-name()="blip"]', $pic);
            if ($blip2 !== false && $blip2->length > 0) {
                $embed = $blip2->item(0)->getAttribute('r:embed');
            }
        }
        if (! $embed) {
            return null;
        }
        $relsFile = 'xl/drawings/_rels/'.basename($drawingFile).'.rels';
        $relsXml = $zip->getFromName($relsFile);
        if ($relsXml === false) {
            return null;
        }
        $rdom = new DOMDocument;
        if (! @$rdom->loadXML($relsXml)) {
            return null;
        }
        $rxp = new DOMXPath($rdom);
        $rxp->registerNamespace('r', self::REL_NS);
        $rel = $rxp->query('//r:Relationship[@Id="'.$embed.'"]');
        if ($rel === false || $rel->length === 0) {
            return null;
        }
        $target = $rel->item(0)->getAttribute('Target');
        $mediaFile = 'xl/'.ltrim(str_replace('../', '', $target), '/');
        if (strpos($target, 'media/') === 0) {
            $mediaFile = 'xl/'.$target;
        } elseif (strpos($target, '../media/') === 0) {
            $mediaFile = 'xl/drawings/../media/'.basename($target);
            $mediaFile = 'xl/media/'.basename($target);
        }
        $bytes = $zip->getFromName($mediaFile);
        if ($bytes === false) {
            return null;
        }
        $mime = str_ends_with(strtolower($mediaFile), '.png') ? 'image/png' : 'image/jpeg';

        $geom = ['x' => 0, 'y' => 0, 'w' => 0, 'h' => 0];
        $xfrm = $xp->query('.//xdr:spPr/a:xfrm', $pic);
        if ($xfrm !== false && $xfrm->length > 0) {
            $g = $this->shapeGeometry($xp, $pic);
            if ($g) {
                $geom = $g;
            }
        }

        return array_merge($geom, [
            'dataUri' => 'data:'.$mime.';base64,'.base64_encode($bytes),
        ]);
    }

    protected function imageDataUri(PatientRecord $record, string $slot): ?string
    {
        $column = PlaceholderMap::slotColumn($slot);
        $path = $column ? $record->$column : null;
        if (! $path || ! Storage::disk('private')->exists($path)) {
            return null;
        }
        $abs = Storage::disk('private')->path($path);
        $info = @getimagesize($abs);
        $mime = $info['mime'] ?? 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($abs));
    }

    /** @return string[] */
    protected function listFiles(ZipArchive $zip, string $regex): array
    {
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match($regex, $name)) {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }
}
