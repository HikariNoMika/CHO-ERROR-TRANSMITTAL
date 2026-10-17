<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\XlsxDirectGenerationService;
use App\Services\XlsxTemplatePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A template can print the form on one page and the uploaded photos on the
 * next. Each worksheet owns its own drawing part, so the preview has to render
 * one page per sheet (in worksheet order) instead of flattening every drawing
 * onto a single canvas.
 */
class MultiPageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Template $template;

    private function seedTwoSheetTemplate(): string
    {
        $name = 'two-pages.xlsx';
        $path = Storage::disk('private')->path('templates/'.$name);
        $this->writeTemplate($path);

        $this->user = User::factory()->create();
        $this->user->forceFill(['is_active' => true])->save();

        $this->template = Template::create([
            'name' => $name,
            'file_path' => 'templates/'.$name,
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        return $path;
    }

    /**
     * Sheet1 carries the form text box, Sheet2 carries the three image slots.
     * Geometry lives in a:xfrm because the preview reads shape transforms.
     */
    private function writeTemplate(string $absPath): void
    {
        $dir = dirname($absPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $z = new \ZipArchive;
        $z->open($absPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'</Types>');

        $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Form" sheetId="1" r:id="rId1"/>'
            .'<sheet name="Photos" sheetId="2" r:id="rId2"/>'
            .'</sheets></workbook>');

        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'</Relationships>');

        foreach ([1, 2] as $n) {
            $z->addFromString("xl/worksheets/sheet{$n}.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<drawing r:id="rId1"/></worksheet>');

            $z->addFromString("xl/worksheets/_rels/sheet{$n}.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                ."<Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing\" Target=\"../drawings/drawing{$n}.xml\"/>"
                .'</Relationships>');
        }

        $z->addFromString('xl/drawings/drawing1.xml', $this->drawingXml([
            $this->textBox(1, 0, 0, 4, 101, '{{patient_name}}'),
        ]));

        $z->addFromString('xl/drawings/drawing2.xml', $this->drawingXml([
            $this->textBox(2, 0, 0, 4, 101, '{{image_with_id}}'),
            $this->textBox(3, 0, 5, 4, 106, '{{id_proof}}'),
            $this->textBox(4, 0, 10, 4, 111, '{{empanelment_error}}'),
        ]));

        $z->close();
    }

    /** @param string[] $anchors */
    private function drawingXml(array $anchors): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .implode('', $anchors)
            .'</xdr:wsDr>';
    }

    private function textBox(int $id, int $col, int $row, int $colTo, int $rowTo, string $text): string
    {
        return '<xdr:twoCellAnchor>'
            ."<xdr:from><xdr:col>{$col}</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>{$row}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>"
            ."<xdr:to><xdr:col>{$colTo}</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>{$rowTo}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>"
            ."<xdr:sp><xdr:nvSpPr><xdr:cNvPr id=\"{$id}\" name=\"Box\"/>"
            .'<xdr:cNvSpPr><a:spLocks noTextEdit="1"/></xdr:cNvSpPr></xdr:nvSpPr>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1000000" cy="1000000"/></a:xfrm></xdr:spPr>'
            ."<xdr:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$text}</a:t></a:r></a:p></xdr:txBody>"
            .'</xdr:sp><xdr:clientData/></xdr:twoCellAnchor>';
    }

    private function pngBytes(int $seed): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return substr($png, 0, 20).$seed.substr($png, 21);
    }

    private function makeRecord(): PatientRecord
    {
        Storage::disk('private')->put('uploads/id.png', $this->pngBytes(1));
        Storage::disk('private')->put('uploads/proof.png', $this->pngBytes(2));
        Storage::disk('private')->put('uploads/error.png', $this->pngBytes(3));

        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'record_type' => 'error',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'pcu_error_code' => 'E-TEST',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
            'image_with_id_path' => 'uploads/id.png',
            'id_proof_image_path' => 'uploads/proof.png',
            'empanelment_error_image_path' => 'uploads/error.png',
        ]);
    }

    public function test_the_preview_renders_one_page_per_sheet_in_worksheet_order(): void
    {
        Storage::fake('private');
        $this->seedTwoSheetTemplate();
        $record = $this->makeRecord();

        $preview = app(XlsxTemplatePreviewService::class)->render($record);

        $this->assertNotNull($preview, 'a text-box template must produce a preview');
        $this->assertCount(2, $preview['pages']);

        [$form, $photos] = $preview['pages'];

        $formKinds = array_column($form['boxes'], 'kind');
        $this->assertContains('text', $formKinds, 'page 1 is the form');
        $this->assertNotContains('image', $formKinds, 'page 1 must not carry the photos');

        $photoKinds = array_column($photos['boxes'], 'kind');
        $this->assertSame(['image', 'image', 'image'], $photoKinds, 'page 2 holds only the three photos');

        $labels = array_column($photos['boxes'], 'label');
        $this->assertSame(['ID Image', 'ID Proof', 'Empanelment Error Image'], $labels);

        foreach ($photos['boxes'] as $box) {
            $this->assertNotNull($box['src'], $box['label'].' must resolve to an image');
        }

        // The three slots must point at three different uploads.
        $this->assertCount(3, array_unique(array_column($photos['boxes'], 'src')));
    }

    public function test_the_print_view_renders_a_break_between_pages(): void
    {
        Storage::fake('private');
        $this->seedTwoSheetTemplate();
        $record = $this->makeRecord();

        $preview = app(XlsxTemplatePreviewService::class)->render($record);
        $html = view('patient-records.print', ['record' => $record, 'preview' => $preview])->render();

        $this->assertSame(2, substr_count($html, 'class="sheet print-document xlsx-page'), 'both pages must render as sheets');
        $this->assertSame(1, substr_count($html, 'xlsx-page page-break'), 'only the first page breaks to the next');
        $this->assertSame(2, substr_count($html, '--ar:'), 'each page exposes its aspect ratio for print scaling');
        $this->assertStringContainsString('JUAN DELA CRUZ', $html, 'the form page still shows record values');
    }

    public function test_the_generator_embeds_the_photos_on_the_second_sheet(): void
    {
        Storage::fake('private');
        $templatePath = $this->seedTwoSheetTemplate();
        $record = $this->makeRecord();

        $result = app(XlsxDirectGenerationService::class)->generate($record, $templatePath);
        $out = Storage::disk('private')->path($result['path']);

        $z = new \ZipArchive;
        $z->open($out);
        $form = (string) $z->getFromName('xl/drawings/drawing1.xml');
        $photos = (string) $z->getFromName('xl/drawings/drawing2.xml');
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        $this->assertStringNotContainsString('{{patient_name}}', $form);
        $this->assertStringNotContainsString('{{image_with_id}}', $photos);
        $this->assertStringNotContainsString('{{id_proof}}', $photos);
        $this->assertStringNotContainsString('{{empanelment_error}}', $photos);

        foreach (['img_id_', 'img_id_proof_', 'img_error_'] as $prefix) {
            $match = array_values(array_filter($names, fn ($n) => str_contains($n, 'xl/media/'.$prefix)));
            $this->assertNotEmpty($match, "the $prefix photo must be embedded");
        }

        $this->assertSame(3, substr_count($photos, '<xdr:pic'), 'the photo sheet needs a picture per slot');
    }

    /**
     * Real camera photos exceed 1600px and get downscaled into a temp file.
     * ZipArchive reads addFile() sources lazily at close() time, so the temp
     * files must outlive the archive. Regression for a generation failure
     * ("ZipArchive::close(): Can't open file") that left records stuck in
     * draft, which hid the Print button.
     */
    public function test_generation_survives_downscaled_large_photos(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension required.');
        }

        Storage::fake('private');
        $templatePath = $this->seedTwoSheetTemplate();

        $im = imagecreatetruecolor(2000, 1200);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);
        $big = (string) ob_get_clean();
        imagedestroy($im);
        Storage::disk('private')->put('uploads/big.png', $big);

        $record = $this->makeRecord();
        $record->forceFill([
            'image_with_id_path' => 'uploads/big.png',
            'id_proof_image_path' => 'uploads/big.png',
            'empanelment_error_image_path' => 'uploads/big.png',
        ])->save();

        $result = app(XlsxDirectGenerationService::class)->generate($record, $templatePath);
        $out = Storage::disk('private')->path($result['path']);
        $this->assertFileExists($out);

        $z = new \ZipArchive;
        $z->open($out);
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        $media = array_values(array_filter($names, fn ($n) => str_contains($n, 'xl/media/img_')));
        $this->assertCount(3, $media, 'all three downscaled photos must be embedded');
    }
}
