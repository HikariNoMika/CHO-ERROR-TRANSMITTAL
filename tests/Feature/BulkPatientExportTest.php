<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Export selected" downloads the ticked rows as a single workbook. It is
 * deliberately read-only: nothing is generated, no file is stored and no status
 * changes, so it is safe to run against records that are already printed.
 */
class BulkPatientExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // The record policy gates on is_active, which the factory leaves unset.
        $this->user->forceFill(['is_active' => true])->save();
        $this->template = Template::create([
            'name' => 'Form',
            'file_path' => 'templates/v1.xlsx',
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    protected function makeRecord(string $name, string $status = 'draft', ?string $file = null): PatientRecord
    {
        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'record_type' => 'error',
            'patient_name' => $name,
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'pcu_error_code' => 'E-'.$name,
            'head_of_clinic' => 'DR. SANTOS',
            'status' => $status,
            'generated_file_path' => $file,
        ]);
    }

    /** Reads the first sheet of a streamed xlsx back into a plain grid. */
    protected function gridFrom(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk').'.xlsx';
        file_put_contents($path, $xlsx);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        unlink($path);

        return $rows;
    }

    public function test_it_downloads_one_workbook_containing_only_the_ticked_rows(): void
    {
        $a = $this->makeRecord('ALPHA');
        $b = $this->makeRecord('BRAVO');
        $untouched = $this->makeRecord('CHARLIE');

        $response = $this->actingAs($this->user)
            ->post(route('records.bulk-export'), ['records' => [$a->id, $b->id]]);

        $response->assertOk();
        $this->assertStringContainsString(
            'MCA_Records_Selected_',
            $response->headers->get('content-disposition')
        );

        $grid = $this->gridFrom($response->streamedContent());
        $names = array_column($grid, 0);

        $this->assertContains('ALPHA', $names);
        $this->assertContains('BRAVO', $names);
        $this->assertNotContains('CHARLIE', $names, 'Unselected records must not be exported.');
    }

    public function test_it_changes_nothing_on_the_records(): void
    {
        $generated = $this->makeRecord('PRINTED ONE', 'printed', 'out/printed.xlsx');
        $draft = $this->makeRecord('DRAFT ONE', 'draft');

        $before = PatientRecord::withoutEvents(fn () => PatientRecord::query()
            ->orderBy('id')->get(['id', 'status', 'generated_file_path'])->all());

        $this->actingAs($this->user)
            ->post(route('records.bulk-export'), ['records' => [$generated->id, $draft->id]])
            ->assertOk();

        $after = PatientRecord::withoutEvents(fn () => PatientRecord::query()
            ->orderBy('id')->get(['id', 'status', 'generated_file_path'])->all());

        $this->assertEquals($before, $after, 'Export must not flip status or attach a file.');
    }

    public function test_ids_keep_the_order_they_were_ticked_in(): void
    {
        $first = $this->makeRecord('FIRST');
        $second = $this->makeRecord('SECOND');
        $third = $this->makeRecord('THIRD');

        $response = $this->actingAs($this->user)->post(route('records.bulk-export'), [
            'records' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();
        $grid = $this->gridFrom($response->streamedContent());

        // Row 0 is the title, rows 1-2 the subtitle, row 3 the header.
        $this->assertSame(['THIRD', 'FIRST', 'SECOND'], array_slice(array_column($grid, 0), 3, 3));
    }

    public function test_it_refuses_an_empty_selection(): void
    {
        $this->actingAs($this->user)
            ->post(route('records.bulk-export'), ['records' => []])
            ->assertSessionHasErrors('records');
    }

    public function test_it_ignores_ids_that_no_longer_exist(): void
    {
        $real = $this->makeRecord('REAL ONE');
        $ghostId = $real->id + 5000;

        $response = $this->actingAs($this->user)
            ->post(route('records.bulk-export'), ['records' => [$real->id, $ghostId]]);

        $response->assertOk();
        $names = array_column($this->gridFrom($response->streamedContent()), 0);
        $this->assertSame(['REAL ONE'], array_values(array_filter(
            $names,
            fn ($n) => in_array($n, ['REAL ONE'], true)
        )));
    }
}