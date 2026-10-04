<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\XlsxDirectGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Template $template;

    private function seedTemplateWithBackground(string $name): string
    {
        $path = Storage::disk('private')->path('templates/'.$name);
        $this->writeTemplateZip($path);

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
     * Minimal package that mirrors the real template: a drawing holding a
     * background picture with two relationships, plus an ID placeholder shape.
     */
    private function writeTemplateZip(string $absPath): void
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
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<drawing r:id="rId1"/></worksheet>');

        $z->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            .'</Relationships>');

        // Two pictures: the template's own background image, and an ID slot to replace.
        $z->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<xdr:twoCellAnchor>'
            .'<xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:to><xdr:col>4</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>6</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
            .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Background"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            .'<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="100" cy="100"/></a:xfrm></xdr:spPr>'
            .'</xdr:pic><xdr:clientData/></xdr:twoCellAnchor>'
            .'<xdr:twoCellAnchor>'
            .'<xdr:from><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>2</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:to><xdr:col>3</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>5</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
            .'<xdr:sp><xdr:nvSpPr><xdr:cNvPr id="3" name="ID Image"/>'
            .'<xdr:cNvSpPr><a:spLocks noTextEdit="1"/></xdr:cNvSpPr></xdr:nvSpPr>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="100" cy="100"/></a:xfrm></xdr:spPr>'
            .'<xdr:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{{image_with_id}}</a:t></a:r></a:p></xdr:txBody>'
            .'</xdr:sp><xdr:clientData/></xdr:twoCellAnchor>'
            .'<xdr:twoCellAnchor>'
            .'<xdr:from><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>6</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:to><xdr:col>3</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>9</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
            .'<xdr:sp><xdr:nvSpPr><xdr:cNvPr id="4" name="Empanelment Error"/>'
            .'<xdr:cNvSpPr><a:spLocks noTextEdit="1"/></xdr:cNvSpPr></xdr:nvSpPr>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="100" cy="100"/></a:xfrm></xdr:spPr>'
            .'<xdr:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{{empanelment_error}}</a:t></a:r></a:p></xdr:txBody>'
            .'</xdr:sp><xdr:clientData/></xdr:twoCellAnchor>'
            .'<xdr:twoCellAnchor>'
            .'<xdr:from><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>10</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:to><xdr:col>3</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>13</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
            .'<xdr:sp><xdr:nvSpPr><xdr:cNvPr id="5" name="ID Proof"/>'
            .'<xdr:cNvSpPr><a:spLocks noTextEdit="1"/></xdr:cNvSpPr></xdr:nvSpPr>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="100" cy="100"/></a:xfrm></xdr:spPr>'
            .'<xdr:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{{id_proof}}</a:t></a:r></a:p></xdr:txBody>'
            .'</xdr:sp><xdr:clientData/></xdr:twoCellAnchor>'
            .'</xdr:wsDr>');

        $z->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>'
            .'<Relationship Id="rId2" Type="http://schemas.microsoft.com/office/2007/relationships/hdphoto" Target="../media/hdphoto1.wdp"/>'
            .'</Relationships>');

        $z->addFromString('xl/media/image1.png', 'PNG-BACKGROUND');
        $z->addFromString('xl/media/hdphoto1.wdp', 'WDP-HD');
        $z->close();
    }

    private function pngBytes(int $seed): string
    {
        // 1x1 png whose payload differs per seed, so two images never collide.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return substr($png, 0, 20).$seed.substr($png, 21);
    }

    public function test_template_relationships_survive_two_image_insertions(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $templatePath = $this->seedTemplateWithBackground('images.xlsx');

        Storage::disk('private')->put('uploads/id.png', $this->pngBytes(1));
        Storage::disk('private')->put('uploads/error.png', $this->pngBytes(2));

        $record = PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'record_type' => 'error',
            'patient_name' => 'Test Patient',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'pcu_error_code' => 'E-TEST',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
            'image_with_id_path' => 'uploads/id.png',
            'empanelment_error_image_path' => 'uploads/error.png',
        ]);

        $result = app(XlsxDirectGenerationService::class)->generate($record, $templatePath);

        $out = Storage::disk('private')->path($result['path']);

        $z = new \ZipArchive;
        $z->open($out);
        $rels = $z->getFromName('xl/drawings/_rels/drawing1.xml.rels');
        $drawing = $z->getFromName('xl/drawings/drawing1.xml');
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        $dom = new \DOMDocument;
        $dom->loadXML($rels);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $found = [];
        foreach ($xp->query('//r:Relationship') as $rel) {
            $found[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }

        // The template's own two relationships must survive both insertions.
        $this->assertSame('../media/image1.png', $found['rId1'] ?? null, 'background image relationship was dropped');
        $this->assertSame('../media/hdphoto1.wdp', $found['rId2'] ?? null, 'hdphoto relationship was dropped');

        // Each embedded image needs its own distinct relationship.
        $embedded = array_values(array_filter($found, fn ($t) => str_contains($t, 'img_id_') || str_contains($t, 'img_error_')));
        $this->assertCount(2, $embedded, 'both inserted images need a relationship');
        $this->assertCount(2, array_unique($embedded), 'inserted images must not share a target');

        // Every relationship must resolve to a real part.
        foreach ($found as $id => $target) {
            $this->assertContains('xl/media/'.basename($target), $names, "relationship $id points at a missing part");
        }

        // r:embed must be a declared namespaced attribute on a:blip in DrawingML main.
        $ddom = new \DOMDocument;
        $ddom->loadXML($drawing);
        $dxp = new \DOMXPath($ddom);
        $dxp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $dxp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        // Match by local name so an element in the wrong namespace is still inspected
        // rather than silently skipped.
        $blips = $dxp->query('//*[local-name()="blip"]');
        $this->assertGreaterThanOrEqual(3, $blips->length, 'expected the template blip plus one per inserted image');

        foreach ($blips as $blip) {
            $this->assertSame(
                'http://schemas.openxmlformats.org/drawingml/2006/main',
                $blip->namespaceURI,
                'a:blip must live in the DrawingML main namespace, got '.$blip->namespaceURI
            );
            $this->assertNotSame(
                '',
                $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed'),
                'r:embed must be a declared namespaced attribute, not a bare "r:embed" literal'
            );
        }

        // The whole part must parse: an undeclared prefix would fail here.
        libxml_use_internal_errors(true);
        $reparsed = new \DOMDocument;
        $this->assertTrue($reparsed->loadXML($drawing), 'drawing part must be well-formed XML');
        libxml_clear_errors();
    }

    /**
     * A medical mission prints the patient holding their ID and a photo of the
     * ID document itself. Both are photos the user uploaded, so each needs its
     * own media part and relationship rather than sharing a target.
     */
    public function test_a_mission_embeds_the_id_photo_and_the_id_proof_separately(): void
    {
        Storage::fake('private');
        $templatePath = $this->seedTemplateWithBackground('mission.xlsx');
        $this->template->update(['record_type' => 'mission']);

        Storage::disk('private')->put('uploads/id.png', $this->pngBytes(1));
        Storage::disk('private')->put('uploads/proof.png', $this->pngBytes(3));

        $record = PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'record_type' => 'mission',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
            'image_with_id_path' => 'uploads/id.png',
            'id_proof_image_path' => 'uploads/proof.png',
        ]);

        $result = app(XlsxDirectGenerationService::class)->generate($record, $templatePath);

        $out = Storage::disk('private')->path($result['path']);

        $z = new \ZipArchive;
        $z->open($out);
        $drawing = $z->getFromName('xl/drawings/drawing1.xml');
        $rels = $z->getFromName('xl/drawings/_rels/drawing1.xml.rels');
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        // No raw placeholder may survive into the printed sheet.
        $this->assertStringNotContainsString('{{id_proof}}', $drawing);
        $this->assertStringNotContainsString('{{image_with_id}}', $drawing);

        $dom = new \DOMDocument;
        $dom->loadXML($rels);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $targets = [];
        foreach ($xp->query('//r:Relationship') as $rel) {
            $targets[$rel->getAttribute('Target')] = true;
        }

        $this->assertMatchesRegularExpression(
            '#\.\./media/img_id_[a-z0-9]{8}\.png$#',
            $this->firstTargetMatching($targets, '#img_id_[a-z0-9]{8}\.png$#'),
            'the ID photo must be embedded'
        );
        $this->assertMatchesRegularExpression(
            '#\.\./media/img_id_proof_[a-z0-9]{8}\.png$#',
            $this->firstTargetMatching($targets, '#img_id_proof_[a-z0-9]{8}\.png$#'),
            'the ID proof must be embedded'
        );

        foreach (array_keys($targets) as $target) {
            $this->assertContains('xl/media/'.basename($target), $names, "relationship points at a missing part: $target");
        }

        // The proof's own bytes must be in the package, not just its filename.
        $proofTarget = $this->firstTargetMatching($targets, '#img_id_proof_[a-z0-9]{8}\.png$#');
        $z2 = new \ZipArchive;
        $z2->open($out);
        $proof = $z2->getFromName('xl/media/'.basename($proofTarget));
        $z2->close();
        $this->assertNotSame('', (string) $proof, 'the ID proof image part is empty');
        $this->assertStringContainsString('PNG', (string) $proof);

        // The two photos must not collapse onto one shared media part.
        $idTarget = $this->firstTargetMatching($targets, '#img_id_[a-z0-9]{8}\.png$#');
        $this->assertNotSame($idTarget, $proofTarget, 'the ID photo and the ID proof need separate media parts');
    }

    /** @param array<string, true> $targets */
    private function firstTargetMatching(array $targets, string $regex): string
    {
        foreach (array_keys($targets) as $target) {
            if (preg_match($regex, (string) $target)) {
                return (string) $target;
            }
        }

        return '';
    }
}
