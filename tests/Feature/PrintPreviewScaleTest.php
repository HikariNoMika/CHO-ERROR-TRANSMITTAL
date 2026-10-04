<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The print preview draws shape geometry as a percentage of the preview width, so
 * text has to scale with it. Fixed point sizes shrink relative to their box on
 * narrow or printed output, wrap onto a second line and get clipped by the
 * overflow:hidden shape, which is why the preview looked right on a wide monitor
 * but printed a truncated name.
 */
class PrintPreviewScaleTest extends TestCase
{
    use RefreshDatabase;

    protected function renderPreview(float $width, float $height): string
    {
        $user = User::factory()->create();
        $template = Template::create([
            'name' => 'Form',
            'file_path' => 'templates/v1.xlsx',
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $record = PatientRecord::create([
            'created_by' => $user->id,
            'template_id' => $template->id,
            'record_type' => 'error',
            'patient_name' => 'ALONGNAME, SAMPLE FOR TESTING',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
            'generated_file_path' => 'out/x.xlsx',
        ]);

        $preview = [
            'background' => null,
            'width' => $width,
            'height' => $height,
            'bgX' => 0, 'bgY' => 0, 'bgW' => $width, 'bgH' => $height,
            'boxes' => [[
                'kind' => 'text',
                'x' => 10.0, 'y' => 20.0, 'w' => 200.0, 'h' => 28.0,
                'lines' => [[
                    'text' => $record->patient_name,
                    'align' => 'left',
                    'style' => ['size' => 14.0, 'bold' => false, 'color' => '000000'],
                ]],
            ]],
        ];

        return view('patient-records.print', ['record' => $record, 'preview' => $preview])->render();
    }

    /** Pulls the rendered font-size declaration back out of the markup. */
    protected function fontSizeInCqw(string $html): float
    {
        $this->assertMatchesRegularExpression(
            '/font-size:([\d.]+)cqw/',
            $html,
            'Preview text must be sized in cqw so it scales with the sheet.'
        );
        preg_match('/font-size:([\d.]+)cqw/', $html, $m);

        return (float) $m[1];
    }

    public function test_text_is_sized_relative_to_the_sheet_not_in_points(): void
    {
        $html = $this->renderPreview(922.5, 877.0);

        $this->assertStringNotContainsString('font-size:14pt', $html);

        // 14pt = 18.667px at 96dpi, which is 2.024% of a 922.5px sheet.
        $this->assertEqualsWithDelta(2.0241, $this->fontSizeInCqw($html), 0.001);
    }

    public function test_the_same_text_covers_the_same_width_at_any_preview_size(): void
    {
        $wide = $this->fontSizeInCqw($this->renderPreview(922.5, 877.0));
        $half = $this->fontSizeInCqw($this->renderPreview(461.25, 438.5));

        // A half-width sheet needs double the container-relative size to end up
        // covering the same share of the sheet.
        $this->assertEqualsWithDelta($wide * 2, $half, 0.001);
    }

    public function test_the_preview_is_a_container_so_cqw_resolves_against_the_sheet(): void
    {
        $layout = view('layouts.print')->render();

        $this->assertStringContainsString(
            'container-type: inline-size',
            $layout,
            'Without a container, cqw would resolve against the viewport instead of the sheet.'
        );
    }
}