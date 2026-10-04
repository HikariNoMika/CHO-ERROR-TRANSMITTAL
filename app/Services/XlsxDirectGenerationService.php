<?php

namespace App\Services;

use App\Models\PatientRecord;
use App\Models\Setting;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Generates completed XLSX documents by editing the template file in place.
 *
 * Used for templates whose placeholders live in floating text boxes
 * (drawingML shapes) rather than worksheet cells. A PhpSpreadsheet
 * round-trip drops those shapes, so the template is copied and its XML
 * parts (drawings, shared strings, inline strings) are edited directly.
 * Everything else in the file — background pictures, formatting, print
 * settings — is preserved byte-for-byte.
 */
class XlsxDirectGenerationService
{
    const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    const SHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    protected array $warnings = [];

    protected array $unknownPlaceholders = [];

    /** @var array<string, DOMDocument> In-memory copies of parts mutated mid-generation. */
    protected array $partCache = [];

    public function __construct(protected TextAutofitService $autofit) {}

    /**
     * @return array{path: string, warnings: string[]}
     */
    public function generate(PatientRecord $record, string $templateAbsPath): array
    {
        $this->warnings = [];
        $this->unknownPlaceholders = [];
        $this->partCache = [];

        $record->loadMissing('template');
        $values = $this->textValues($record);
        $images = $this->imagePaths($record);

        $relDir = 'generated-documents/'.now()->format('Y/m');
        Storage::disk('private')->makeDirectory($relDir);
        $filename = $this->filenameFor($record);
        $relPath = "{$relDir}/{$filename}";
        $absPath = Storage::disk('private')->path($relPath);

        if (! copy($templateAbsPath, $absPath)) {
            throw new \RuntimeException('Could not copy template for generation.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absPath) !== true) {
            throw new \RuntimeException('Could not open working copy for generation.');
        }

        try {
            $this->replaceInSharedStrings($zip, $values);
            $this->replaceInInlineStrings($zip, $values);
            $this->processDrawings($zip, $values, $images);
        } finally {
            $zip->close();
        }

        foreach (array_keys($this->unknownPlaceholders) as $unknown) {
            $this->warnings[] = 'Unknown placeholder {{'.$unknown.'}} was left blank.';
        }

        return ['path' => $relPath, 'warnings' => $this->warnings];
    }

    // ------------------------------------------------------------------
    // Values
    // ------------------------------------------------------------------

    /** Resolved canonical placeholder values for a record (shared with preview). */
    public function resolveValues(PatientRecord $record): array
    {
        $record->loadMissing('template');

        return $this->textValues($record);
    }

    protected function textValues(PatientRecord $record): array
    {
        return [
            'patient_name' => (string) $record->patient_name,
            'birthdate' => $record->birthdate?->format('m-d-y') ?? '',
            'philhealth_id' => (string) $record->philhealth_id,
            'head_of_clinic' => (string) $record->head_of_clinic,
            'date_today' => ($record->date_today ?? now())->format('m-d-y'),
            'facility_name' => Setting::getFacilityName(),
            'facility_address' => Setting::getFacilityAddress(),
            'appointment_date' => $record->appointment_date?->format('m-d-y') ?? '',
            'auth_transaction_code' => (string) ($record->auth_transaction_code ?? ''),
            'pcu_error_code' => (string) ($record->pcu_error_code ?? ''),
        ];
    }

    /**
     * slot => absolute image path, for every slot this record can supply.
     *
     * Driven by the placeholder map so a new image slot only needs a column
     * and a map entry.
     */
    protected function imagePaths(PatientRecord $record): array
    {
        $images = [];
        foreach (['id', 'error', 'id_proof'] as $slot) {
            $column = PlaceholderMap::slotColumn($slot);
            $stored = $column ? $record->$column : null;
            if ($stored && Storage::disk('private')->exists($stored)) {
                $images[$slot] = Storage::disk('private')->path($stored);
            }
        }

        return $images;
    }

    protected function resolveText(string $placeholder, array $values): string
    {
        $canonical = PlaceholderMap::canonicalText($placeholder);
        if ($canonical !== null) {
            return $values[$canonical] ?? '';
        }
        $this->unknownPlaceholders[$placeholder] = true;

        return '';
    }

    protected function filenameFor(PatientRecord $record): string
    {
        $sanitized = Str::slug($record->patient_name) ?: 'patient';
        $dateStr = now()->format('Y-m-d');
        $uniqueId = Str::upper(Str::random(6));

        return "EMPANELMENT_{$sanitized}_{$dateStr}_{$uniqueId}.xlsx";
    }

    // ------------------------------------------------------------------
    // Generic run-aware replacement
    // ------------------------------------------------------------------

    /**
     * Replace {{placeholders}} inside each container's combined run text.
     * The replaced text is written back into the first text node and the
     * remaining runs are cleared, so placeholders split across runs
     * (e.g. {{person_ + fullname}}) are still resolved.
     */
    protected function replaceInContainers(DOMXPath $xp, iterable $containers, string $textQuery, callable $resolve): void
    {
        foreach ($containers as $container) {
            $nodes = $xp->query($textQuery, $container);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }
            $combined = '';
            foreach ($nodes as $t) {
                $combined .= $t->nodeValue;
            }
            if (strpos($combined, '{{') === false) {
                continue;
            }
            $matched = [];
            $replaced = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($resolve, &$matched) {
                $matched[] = $m[1];

                return $resolve($m[1]);
            }, $combined);
            if ($replaced === $combined) {
                continue;
            }
            $nodes->item(0)->nodeValue = $replaced;
            for ($i = 1; $i < $nodes->length; $i++) {
                $nodes->item($i)->nodeValue = '';
            }
        }
    }

    /**
     * Shrinks the font of a shape's text when the value is too wide for the box,
     * leaving the shape itself exactly where and as large as the template put it.
     *
     * Excel only recalculates shape autofit when the shape is edited, so the
     * scale is computed here and written into the drawing.
     */
    protected function autofitShapeText(DOMXPath $xp, \DOMNode $paragraph, string $text, array $matchedPlaceholders): void
    {
        $config = config('mca.autofit');
        if (! ($config['enabled'] ?? true) || trim($text) === '') {
            return;
        }
        if (! $this->shouldAutofit($matchedPlaceholders, $config['fields'])) {
            return;
        }

        // The container is a paragraph; geometry lives on the ancestor shape.
        $shape = $xp->query('ancestor::xdr:sp', $paragraph)->item(0);
        if ($shape === null) {
            return;
        }

        $ext = $xp->query('./xdr:spPr/a:xfrm/a:ext', $shape)->item(0);
        $bodyPr = $xp->query('./xdr:txBody/a:bodyPr', $shape)->item(0);
        if ($ext === null || $bodyPr === null) {
            return;
        }

        $runProps = $xp->query('.//a:rPr', $shape)->item(0);
        $fontSize = $runProps && $runProps->hasAttribute('sz')
            ? ((int) $runProps->getAttribute('sz')) / 100
            : 11.0;
        $bold = $runProps && in_array($runProps->getAttribute('b'), ['1', 'true'], true);

        $scale = $this->autofit->fontScaleFor(
            $text,
            $fontSize,
            $this->autofit->availableWidthInPoints(
                (int) $ext->getAttribute('cx'),
                $bodyPr->hasAttribute('lIns') ? (int) $bodyPr->getAttribute('lIns') : TextAutofitService::DEFAULT_L_INS,
                $bodyPr->hasAttribute('rIns') ? (int) $bodyPr->getAttribute('rIns') : TextAutofitService::DEFAULT_R_INS
            ) * ($config['width_factor'] ?? 1.0),
            $bold,
            (float) $config['min_font_pt'],
            (float) $config['tolerance']
        );

        if ($scale === null) {
            return;
        }

        // Autofit is inert while wrap is "none", so fall back to the default.
        if ($bodyPr->getAttribute('wrap') === 'none') {
            $bodyPr->removeAttribute('wrap');
        }

        // Exactly one autofit child is permitted; drop whatever was there.
        foreach (['a:noAutofit', 'a:normAutofit', 'a:spAutoFit'] as $existing) {
            foreach (iterator_to_array($xp->query($existing, $bodyPr)) as $node) {
                $bodyPr->removeChild($node);
            }
        }

        $norm = $bodyPr->ownerDocument->createElementNS(self::A_NS, 'a:normAutofit');
        $norm->setAttribute('fontScale', (string) $scale);
        $bodyPr->appendChild($norm);

        if ($config['warn'] ?? true) {
            $this->warnings[] = sprintf(
                'Text was condensed to %.1fpt to fit its box: "%s".',
                $fontSize * $scale / 100000,
                $text
            );
        }
    }

    /**
     * True when any matched placeholder belongs to the configured field list.
     *
     * @param  string[]  $fields
     */
    protected function shouldAutofit(array $matchedPlaceholders, array $fields): bool
    {
        if (in_array('*', $fields, true)) {
            return true;
        }
        foreach ($matchedPlaceholders as $name) {
            $canonical = PlaceholderMap::canonicalText($name) ?? $name;
            if (in_array($canonical, $fields, true)) {
                return true;
            }
        }

        return false;
    }

    protected function loadXml(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        if (! @$dom->loadXML($xml)) {
            return null;
        }

        return $dom;
    }

    /**
     * Read an XML part for mutation, keeping the DOM in memory between calls.
     *
     * ZipArchive::getFromName() returns false once addFromString() has rewritten
     * that entry, so parts we touch more than once in a single generation
     * (drawing rels, [Content_Types].xml) cannot be re-read from the archive.
     * Without this cache the second image would rebuild a bare skeleton, restart
     * relationship ids at rId1 and silently drop the template's own entries.
     */
    protected function mutablePart(ZipArchive $zip, string $entry, string $blankXml): ?DOMDocument
    {
        if (! isset($this->partCache[$entry])) {
            $xml = $zip->getFromName($entry);
            $this->partCache[$entry] = $this->loadXml($xml === false ? $blankXml : $xml);
        }

        return $this->partCache[$entry];
    }

    // ------------------------------------------------------------------
    // Cells: shared strings + inline strings
    // ------------------------------------------------------------------

    protected function replaceInSharedStrings(ZipArchive $zip, array $values): void
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $dom = $this->loadXml($xml);
        if (! $dom) {
            return;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('m', self::SHEET_NS);
        $this->replaceInContainers($xp, $xp->query('//m:si'), './/m:t', fn ($ph) => $this->resolveText($ph, $values));
        $zip->addFromString('xl/sharedStrings.xml', $dom->saveXML());
    }

    protected function replaceInInlineStrings(ZipArchive $zip, array $values): void
    {
        foreach ($this->listFiles($zip, '#xl/worksheets/sheet\d+\.xml$#') as $sheetFile) {
            $xml = $zip->getFromName($sheetFile);
            if ($xml === false) {
                continue;
            }
            $dom = $this->loadXml($xml);
            if (! $dom) {
                continue;
            }
            $xp = new DOMXPath($dom);
            $xp->registerNamespace('m', self::SHEET_NS);
            $before = $xml;
            $this->replaceInContainers($xp, $xp->query('//m:is'), './/m:t', fn ($ph) => $this->resolveText($ph, $values));
            $after = $dom->saveXML();
            if ($after !== $before) {
                $zip->addFromString($sheetFile, $after);
            }
        }
    }

    // ------------------------------------------------------------------
    // Drawings: text replacement + image anchoring
    // ------------------------------------------------------------------

    protected function processDrawings(ZipArchive $zip, array $values, array $images): void
    {
        foreach ($this->listFiles($zip, '#xl/drawings/drawing\d+\.xml$#') as $drawingFile) {
            $xml = $zip->getFromName($drawingFile);
            if ($xml === false) {
                continue;
            }
            $dom = $this->loadXml($xml);
            if (! $dom) {
                continue;
            }
            $xp = new DOMXPath($dom);
            $xp->registerNamespace('a', self::A_NS);
            $xp->registerNamespace('xdr', self::DRAWING_NS);

            $this->replaceImageShapes($zip, $dom, $xp, $drawingFile, $images);

            // Text pass over the remaining shapes, paragraph by paragraph
            // (a paragraph may span several runs, so rewrite per <a:p>).
            foreach ($xp->query('//a:p') as $para) {
                $nodes = $xp->query('.//a:t', $para);
                if ($nodes === false || $nodes->length === 0) {
                    continue;
                }
                $combined = '';
                foreach ($nodes as $t) {
                    $combined .= $t->nodeValue;
                }
                if (strpos($combined, '{{') === false) {
                    continue;
                }
                $matched = [];
                $replaced = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($values, &$matched) {
                    $matched[] = $m[1];

                    return $this->resolveText($m[1], $values);
                }, $combined);
                if ($replaced === $combined) {
                    continue;
                }
                $nodes->item(0)->nodeValue = $replaced;
                for ($i = 1; $i < $nodes->length; $i++) {
                    $nodes->item($i)->nodeValue = '';
                }
                $this->autofitShapeText($xp, $para, $replaced, $matched);
            }

            $zip->addFromString($drawingFile, $dom->saveXML());
        }
    }

    /**
     * Replace text-box shapes holding image placeholders with anchored pictures.
     */
    protected function replaceImageShapes(ZipArchive $zip, DOMDocument $dom, DOMXPath $xp, string $drawingFile, array $images): void
    {
        $anchors = [];
        foreach ($xp->query('//xdr:twoCellAnchor | //xdr:oneCellAnchor') as $anchor) {
            $anchors[] = $anchor;
        }

        $maxId = 0;
        foreach ($xp->query('//xdr:cNvPr') as $cNvPr) {
            $maxId = max($maxId, (int) $cNvPr->getAttribute('id'));
        }

        foreach ($anchors as $anchor) {
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

            $text = '';
            foreach ($xp->query('.//a:t', $shape) as $t) {
                $text .= $t->nodeValue;
            }
            preg_match_all('/\{\{(\w+)\}\}/', $text, $m);
            $slot = null;
            foreach ($m[1] ?? [] as $ph) {
                $slot = PlaceholderMap::imageSlot($ph);
                if ($slot !== null) {
                    break;
                }
            }
            if ($slot === null) {
                continue;
            }

            if (! isset($images[$slot])) {
                // No image supplied: drop the shape so no raw {{placeholder}} prints.
                $anchor->parentNode->removeChild($anchor);
                $this->warnings[] = PlaceholderMap::slotLabel($slot).' was not provided; its area was left blank.';

                continue;
            }

            $maxId++;
            $this->embedPicture($zip, $dom, $xp, $drawingFile, $anchor, $shape, $slot, $images[$slot], $maxId);
            $anchor->parentNode->removeChild($anchor);
        }
    }

    protected function embedPicture(
        ZipArchive $zip,
        DOMDocument $dom,
        DOMXPath $xp,
        string $drawingFile,
        $anchor,
        $shape,
        string $slot,
        string $imageAbsPath,
        int $cNvId
    ): void {
        // Anchor geometry: normalise oneCellAnchor to from/to.
        $from = $xp->query('./xdr:from', $anchor)->item(0);
        $toNodes = $xp->query('./xdr:to', $anchor);
        if ($toNodes !== false && $toNodes->length > 0) {
            $to = $toNodes->item(0);
        } else {
            // oneCellAnchor: derive `to` from from + ext.
            $ext = $xp->query('./xdr:ext', $anchor)->item(0);
            $to = $dom->createElementNS(self::DRAWING_NS, 'xdr:to');
            $to->appendChild($dom->createElementNS(self::DRAWING_NS, 'xdr:col', $this->nodeText($xp, './xdr:col', $from)));
            $to->appendChild($dom->createElementNS(self::DRAWING_NS, 'xdr:colOff', (string) ((int) $this->nodeText($xp, './xdr:colOff', $from) + (int) $ext->getAttribute('cx'))));
            $to->appendChild($dom->createElementNS(self::DRAWING_NS, 'xdr:row', $this->nodeText($xp, './xdr:row', $from)));
            $to->appendChild($dom->createElementNS(self::DRAWING_NS, 'xdr:rowOff', (string) ((int) $this->nodeText($xp, './xdr:rowOff', $from) + (int) $ext->getAttribute('cy'))));
        }

        // Picture geometry: reuse the text box's own transform so the
        // picture fills exactly the box the designer drew.
        $xfrmNodes = $xp->query('.//xdr:spPr/a:xfrm', $shape);
        $xfrm = $xfrmNodes !== false && $xfrmNodes->length > 0 ? $xfrmNodes->item(0) : null;

        [$mediaTarget, $relId] = $this->addImageMedia($zip, $drawingFile, $slot, $imageAbsPath);

        $wsDr = $dom->documentElement;
        $newAnchor = $dom->createElementNS(self::DRAWING_NS, 'xdr:twoCellAnchor');
        $newAnchor->appendChild($dom->importNode($from, true));
        $newAnchor->appendChild($dom->importNode($to, true));

        $pic = $dom->createElementNS(self::DRAWING_NS, 'xdr:pic');

        $nvPicPr = $dom->createElementNS(self::DRAWING_NS, 'xdr:nvPicPr');
        $cNvPr = $dom->createElementNS(self::DRAWING_NS, 'xdr:cNvPr');
        $cNvPr->setAttribute('id', (string) $cNvId);
        $cNvPr->setAttribute('name', PlaceholderMap::slotLabel($slot));
        $nvPicPr->appendChild($cNvPr);
        $cNvPicPr = $dom->createElementNS(self::DRAWING_NS, 'xdr:cNvPicPr');
        $locks = $dom->createElementNS(self::A_NS, 'a:picLocks');
        $locks->setAttribute('noChangeAspect', '1');
        $cNvPicPr->appendChild($locks);
        $nvPicPr->appendChild($cNvPicPr);
        $pic->appendChild($nvPicPr);

        // blipFill is xdr-namespaced here (CT_Picture), but a:blip and a:stretch are not.
        $blipFill = $dom->createElementNS(self::DRAWING_NS, 'xdr:blipFill');
        $blip = $dom->createElementNS(self::A_NS, 'a:blip');
        $blip->setAttributeNS(self::R_NS, 'r:embed', $relId);
        $blipFill->appendChild($blip);
        $stretch = $dom->createElementNS(self::A_NS, 'a:stretch');
        $stretch->appendChild($dom->createElementNS(self::A_NS, 'a:fillRect'));
        $blipFill->appendChild($stretch);
        $pic->appendChild($blipFill);

        $spPr = $dom->createElementNS(self::DRAWING_NS, 'xdr:spPr');
        if ($xfrm) {
            $spPr->appendChild($dom->importNode($xfrm, true));
        } else {
            $fallbackXfrm = $dom->createElementNS(self::A_NS, 'a:xfrm');
            $fallbackXfrm->appendChild($dom->createElementNS(self::A_NS, 'a:off'));
            $fallbackExt = $dom->createElementNS(self::A_NS, 'a:ext');
            $fallbackExt->setAttribute('cx', '2695575');
            $fallbackExt->setAttribute('cy', '3095625');
            $fallbackXfrm->appendChild($fallbackExt);
            $spPr->appendChild($fallbackXfrm);
        }
        $pic->appendChild($spPr);

        $newAnchor->appendChild($pic);
        $newAnchor->appendChild($dom->createElementNS(self::DRAWING_NS, 'xdr:clientData'));
        $wsDr->appendChild($newAnchor);
    }

    protected function nodeText(DOMXPath $xp, string $query, $context): string
    {
        $nodes = $xp->query($query, $context);

        return ($nodes !== false && $nodes->length > 0) ? $nodes->item(0)->nodeValue : '0';
    }

    /**
     * Add the image to xl/media + drawing rels + content types.
     *
     * @return array{0: string, 1: string} [mediaTarget, relId]
     */
    protected function addImageMedia(ZipArchive $zip, string $drawingFile, string $slot, string $imageAbsPath): array
    {
        [$preparedPath, $ext, $cleanup] = $this->prepareImage($imageAbsPath);
        try {
            $existing = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (strpos($n, 'xl/media/') === 0) {
                    $existing[] = $n;
                }
            }
            do {
                $mediaName = 'xl/media/img_'.$slot.'_'.Str::lower(Str::random(8)).'.'.$ext;
            } while (in_array($mediaName, $existing, true));

            if ($zip->addFile($preparedPath, $mediaName) !== true) {
                throw new \RuntimeException('Could not embed image into document.');
            }

            $this->ensureContentType($zip, $ext);

            $relsFile = 'xl/drawings/_rels/'.basename($drawingFile).'.rels';
            $rdom = $this->mutablePart($zip, $relsFile, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="'.self::REL_NS.'"></Relationships>');
            if (! $rdom) {
                throw new \RuntimeException('Could not update drawing relationships.');
            }
            $rxp = new DOMXPath($rdom);
            $rxp->registerNamespace('r', self::REL_NS);
            $maxRid = 0;
            foreach ($rxp->query('//r:Relationship') as $rel) {
                if (preg_match('/rId(\d+)/', $rel->getAttribute('Id'), $mm)) {
                    $maxRid = max($maxRid, (int) $mm[1]);
                }
            }
            $relId = 'rId'.($maxRid + 1);
            $rel = $rdom->createElementNS(self::REL_NS, 'Relationship');
            $rel->setAttribute('Id', $relId);
            $rel->setAttribute('Type', self::R_NS.'/image');
            $rel->setAttribute('Target', '../media/'.basename($mediaName));
            $rdom->documentElement->appendChild($rel);
            $zip->addFromString($relsFile, $rdom->saveXML());

            return [$mediaName, $relId];
        } finally {
            if ($cleanup && is_file($preparedPath)) {
                @unlink($preparedPath);
            }
        }
    }

    protected function ensureContentType(ZipArchive $zip, string $ext): void
    {
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $ctNs = 'http://schemas.openxmlformats.org/package/2006/content-types';
        $dom = $this->mutablePart(
            $zip,
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="'.$ctNs.'"></Types>'
        );
        if (! $dom || ! $dom->documentElement) {
            return;
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('c', $ctNs);
        $found = $xp->query('//c:Default[@Extension="'.$ext.'"]');
        if ($found !== false && $found->length > 0) {
            return;
        }
        $def = $dom->createElementNS($ctNs, 'Default');
        $def->setAttribute('Extension', $ext);
        $def->setAttribute('ContentType', $mime);
        $dom->documentElement->appendChild($def);
        $zip->addFromString('[Content_Types].xml', $dom->saveXML());
    }

    /**
     * Normalise the image for Excel embedding: Excel cannot display webp,
     * so convert to png; downscale very large photos to bound file size.
     *
     * @return array{0: string, 1: string, 2: bool} [path, ext, cleanupTemp]
     */
    protected function prepareImage(string $absPath): array
    {
        $info = @getimagesize($absPath);
        $mime = $info['mime'] ?? '';
        $needsConvert = $mime === 'image/webp'
            || ($info && max($info[0], $info[1]) > 1600);

        if (! $needsConvert) {
            $ext = $mime === 'image/png' ? 'png' : 'jpeg';

            return [$absPath, $ext, false];
        }

        $src = @imagecreatefromstring(@file_get_contents($absPath));
        if (! $src) {
            // GD cannot read it: embed as-is and hope for the best.
            $ext = $mime === 'image/png' ? 'png' : 'jpeg';

            return [$absPath, $ext, false];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 1600 / max($w, $h));
        if ($scale < 1) {
            $dst = imagecreatetruecolor((int) ($w * $scale), (int) ($h * $scale));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, (int) ($w * $scale), (int) ($h * $scale), $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mca_img_');
        if ($mime === 'image/png') {
            imagealphablending($src, false);
            imagesavealpha($src, true);
            imagepng($src, $tmp);
            $ext = 'png';
        } else {
            imagejpeg($src, $tmp, 88);
            $ext = 'jpeg';
        }
        imagedestroy($src);

        return [$tmp, $ext, true];
    }

    /** @return string[] zip-internal file names matching $regex */
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
