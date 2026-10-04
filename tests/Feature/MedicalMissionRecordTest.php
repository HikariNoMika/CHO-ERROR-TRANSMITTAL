<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Support\RecordType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Medical Mission behaves like PCU Error: same list, same create flow, same
 * document. The differences live in App\Support\RecordType and are asserted
 * here so a later tweak to one type cannot quietly change another.
 */
class MedicalMissionRecordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Real files on the private disk: the generator reads the template and
        // the photo from there, so faking it with a stub path would not do.
        Storage::fake('private');

        $this->user = User::factory()->create();
        // Admin so the policy allows create and delete; the factory sets no role.
        $this->user->forceFill(['is_active' => true, 'role' => 'admin'])->save();

        $this->template = Template::create([
            'name' => 'Form',
            'file_path' => 'templates/v1.xlsx',
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        $this->writeMinimalTemplate();
    }

    /** A workbook with just enough structure for the generator to open. */
    protected function writeMinimalTemplate(): void
    {
        Storage::disk('private')->makeDirectory('templates');

        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('A1', 'x');
        $book->getActiveSheet()->setCellValue('B1', 'y');
        (new Xlsx($book))
            ->save(Storage::disk('private')->path($this->template->file_path));
        unset($book);
    }

    /** Stores a genuine 1x1 PNG and returns its private-disk path. */
    protected function storeFakeIdPhoto(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
        $path = 'test-images/id.png';
        Storage::disk('private')->put($path, $png);

        return $path;
    }

    protected function makeRecord(string $type, string $name = 'PATIENT'): PatientRecord
    {
        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'record_type' => $type,
            'patient_name' => $name,
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'pcu_error_code' => 'PCU-ERR-1',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
        ]);
    }

    public function test_the_registry_knows_all_three_types(): void
    {
        $this->assertSame(['error', 'success', 'mission'], RecordType::slugs());
        $this->assertSame('Medical Mission', RecordType::label('mission'));
        $this->assertTrue(RecordType::exists('mission'));
        $this->assertFalse(RecordType::exists('nonsense'));
    }

    public function test_the_mission_list_is_reachable_and_shows_only_mission_records(): void
    {
        $mission = $this->makeRecord('mission', 'MISSION ONE');
        $error = $this->makeRecord('error', 'ERROR ONE');
        $success = $this->makeRecord('success', 'SUCCESS ONE');

        $response = $this->actingAs($this->user)->get(route('records.mission'));

        $response->assertOk();
        $response->assertSee('MISSION ONE');
        $response->assertDontSee('ERROR ONE');
        $response->assertDontSee('SUCCESS ONE');

        $this->assertSame([$mission->id], $response->viewData('records')->pluck('id')->all());
        $this->assertNotNull($error);
        $this->assertNotNull($success);
    }

    public function test_the_sidebar_links_to_the_mission_list(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.mission'));

        $response->assertOk();
        $response->assertSee(route('records.mission'), false);
    }

    public function test_a_mission_record_can_be_created_with_just_an_id_photo(): void
    {
        $response = $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'mission',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->template->id,
        ]);

        // No PCU error code and no error screenshot are demanded for a mission.
        $response->assertSessionHasNoErrors();

        $record = PatientRecord::where('patient_name', 'JUAN DELA CRUZ')->first();
        $this->assertNotNull($record);
        $this->assertSame('mission', $record->record_type);
    }

    public function test_mission_still_requires_the_identifying_fields(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'mission',
            'patient_name' => '',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->template->id,
        ])->assertSessionHasErrors(['patient_name']);

        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'mission',
            'patient_name' => 'NO PIN',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '',
            'template_id' => $this->template->id,
        ])->assertSessionHasErrors(['philhealth_id']);
    }

    public function test_an_unknown_record_type_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'bogus',
            'patient_name' => 'SOMEONE',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->template->id,
        ])->assertSessionHasErrors(['record_type']);
    }

    public function test_success_records_still_demand_their_success_code(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'success',
            'patient_name' => 'NO CODE',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->template->id,
        ])->assertSessionHasErrors(['pcu_error_code']);
    }

    public function test_the_create_page_for_mission_hides_the_error_screenshot(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.mission.create'));

        $response->assertOk();
        $response->assertSee('New Medical Mission Record');
        $response->assertSee('name="image_with_id"', false);
        $response->assertDontSee('name="empanelment_error_image"', false);
        $response->assertDontSee('name="pcu_error_code"', false);
    }

    public function test_the_create_page_for_error_keeps_both_photos(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.error.create'));

        $response->assertOk();
        $response->assertSee('name="image_with_id"', false);
        $response->assertSee('name="empanelment_error_image"', false);
        $response->assertSee('name="pcu_error_code"', false);
    }

    public function test_deleting_a_mission_record_returns_to_the_mission_list(): void
    {
        $mission = $this->makeRecord('mission');

        $this->actingAs($this->user)
            ->delete(route('records.destroy', $mission))
            ->assertRedirect(route('records.mission'));
    }

    public function test_deleting_an_error_record_still_returns_to_the_error_list(): void
    {
        $error = $this->makeRecord('error');

        $this->actingAs($this->user)
            ->delete(route('records.destroy', $error))
            ->assertRedirect(route('records.error'));
    }

    public function test_the_show_page_links_back_to_the_right_list(): void
    {
        $mission = $this->makeRecord('mission');

        $response = $this->actingAs($this->user)->get(route('records.show', $mission));

        $response->assertOk();
        $response->assertSee('Medical Mission');
        $response->assertSee(route('records.mission'), false);
    }

    public function test_the_dashboard_counts_missions_apart_from_the_others(): void
    {
        $this->makeRecord('mission');
        $this->makeRecord('mission', 'SECOND MISSION');
        $this->makeRecord('error');

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $this->assertSame(2, $analytics['mission']);
        $this->assertSame(1, $analytics['error']);
        $this->assertSame(0, $analytics['success']);
        $this->assertSame(3, $analytics['total']);
        $this->assertArrayHasKey('mission', $analytics['series']);
        $response->assertSee('Medical Mission');
    }

    public function test_mission_records_export_with_their_own_rows(): void
    {
        $mission = $this->makeRecord('mission', 'MISSION ROW');
        $error = $this->makeRecord('error', 'ERROR ROW');

        $response = $this->actingAs($this->user)->post(route('records.bulk-export'), [
            'records' => [$mission->id],
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('MISSION ROW', $this->sheetNames($body));
        $this->assertStringNotContainsString('ERROR ROW', $this->sheetNames($body));
    }

    /**
     * The shared template carries an error-image placeholder that a mission has
     * no value for. Generation must skip that slot instead of demanding it,
     * while still demanding the ID image.
     */
    public function test_generation_demands_the_id_image_but_not_an_error_image_for_a_mission(): void
    {
        $this->template->fields()->createMany([
            ['placeholder' => 'image_with_id', 'label' => 'ID', 'type' => 'image', 'is_required' => true, 'sort_order' => 1],
            ['placeholder' => 'empanelment_error', 'label' => 'Error', 'type' => 'image', 'is_required' => true, 'sort_order' => 2],
        ]);

        $mission = $this->makeRecord('mission');
        $mission->forceFill(['image_with_id_path' => $this->storeFakeIdPhoto()])->save();

        // The only required images are satisfied: no error screenshot demanded.
        $this->actingAs($this->user)
            ->from(route('records.show', $mission))
            ->get(route('records.generate', $mission))
            ->assertSessionHasNoErrors();

        // With the ID image missing, the ID slot is still reported.
        $noPhoto = $this->makeRecord('mission', 'NO PHOTO');
        $this->actingAs($this->user)
            ->from(route('records.show', $noPhoto))
            ->get(route('records.generate', $noPhoto))
            ->assertSessionHasErrors(['generation']);

        $this->assertStringContainsString(
            'ID Image',
            session('errors')->first('generation')
        );
        $this->assertStringNotContainsString(
            'Empanelment Error Image',
            session('errors')->first('generation')
        );
    }

    public function test_generation_still_demands_the_error_image_for_a_pcu_error(): void
    {
        $this->template->fields()->createMany([
            ['placeholder' => 'image_with_id', 'label' => 'ID', 'type' => 'image', 'is_required' => true, 'sort_order' => 1],
            ['placeholder' => 'empanelment_error', 'label' => 'Error', 'type' => 'image', 'is_required' => true, 'sort_order' => 2],
        ]);

        $error = $this->makeRecord('error');
        $error->forceFill(['image_with_id_path' => $this->storeFakeIdPhoto()])->save();

        $this->actingAs($this->user)
            ->from(route('records.show', $error))
            ->get(route('records.generate', $error))
            ->assertSessionHasErrors(['generation']);

        $this->assertStringContainsString(
            'Empanelment Error Image',
            session('errors')->first('generation')
        );
    }

    /** Pulls the first column of the streamed workbook as plain text. */
    protected function sheetNames(string $xlsx): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mission').'.xlsx';
        file_put_contents($path, $xlsx);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $text = '';
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $text .= ' '.($row[0] ?? '');
        }
        unlink($path);

        return $text;
    }
}
