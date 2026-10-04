<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\PlaceholderMap;
use App\Support\RecordType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Medical Mission: its own Excel template, its own evidence rules (the ID photo
 * plus a photo of the ID document), and no PCU error screenshot or error code.
 *
 * The differences live in App\Support\RecordType and are asserted here so a
 * later tweak to one type cannot quietly change another.
 */
class MedicalMissionRecordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /** Live template per printing type. */
    protected array $templates = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Real files on the private disk: the generator reads the template and
        // the photos from there, so a stub path would not do.
        Storage::fake('private');

        $this->user = User::factory()->create();
        // Admin so the policy allows create and delete; the factory sets no role.
        $this->user->forceFill(['is_active' => true, 'role' => 'admin'])->save();

        $this->templates = [
            RecordType::ERROR => $this->makeTemplate(RecordType::ERROR),
            RecordType::MISSION => $this->makeTemplate(RecordType::MISSION),
        ];
    }

    /** A live, empty-workbook template for one record type. */
    protected function makeTemplate(string $type): Template
    {
        $path = 'templates/'.$type.'.xlsx';

        Storage::disk('private')->makeDirectory('templates');
        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('A1', 'x');
        (new Xlsx($book))->save(Storage::disk('private')->path($path));
        unset($book);

        return Template::create([
            'name' => ucfirst($type).' form',
            'record_type' => $type,
            'file_path' => $path,
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    /** Declares required placeholders on a type's template. */
    protected function withFields(string $type, array $fields): void
    {
        foreach ($fields as $i => $placeholder) {
            $this->templates[$type]->fields()->create([
                'placeholder' => $placeholder,
                'label' => $placeholder,
                'type' => PlaceholderMap::type($placeholder),
                'is_required' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** Stores a genuine 1x1 PNG and returns its private-disk path. */
    protected function storeFakeIdPhoto(string $name = 'id'): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
        $path = 'test-images/'.$name.'.png';
        Storage::disk('private')->put($path, $png);

        return $path;
    }

    protected function makeRecord(string $type, string $name = 'PATIENT'): PatientRecord
    {
        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => RecordType::usesTemplate($type) ? $this->templates[$type]->id : null,
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

    public function test_only_the_printing_types_own_a_template(): void
    {
        $this->assertTrue(RecordType::usesTemplate('error'));
        $this->assertTrue(RecordType::usesTemplate('mission'));
        $this->assertFalse(RecordType::usesTemplate('success'), 'success is data-only');
        $this->assertSame(['error', 'mission'], RecordType::templateTypes());
    }

    public function test_each_type_resolves_its_own_live_template(): void
    {
        $this->assertSame($this->templates['error']->id, Template::activeFor('error')->id);
        $this->assertSame($this->templates['mission']->id, Template::activeFor('mission')->id);
        $this->assertNull(Template::activeFor('success'), 'data-only types have no template');
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
            'template_id' => $this->templates['mission']->id,
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
            'template_id' => $this->templates['mission']->id,
        ])->assertSessionHasErrors(['patient_name']);

        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'mission',
            'patient_name' => 'NO PIN',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '',
            'template_id' => $this->templates['mission']->id,
        ])->assertSessionHasErrors(['philhealth_id']);
    }

    public function test_an_unknown_record_type_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'bogus',
            'patient_name' => 'SOMEONE',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->templates['mission']->id,
        ])->assertSessionHasErrors(['record_type']);
    }

    public function test_success_records_still_demand_their_success_code(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'success',
            'patient_name' => 'NO CODE',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
        ])->assertSessionHasErrors(['pcu_error_code']);
    }

    public function test_the_create_page_for_mission_asks_for_the_id_proof_and_not_an_error_screenshot(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.mission.create'));

        $response->assertOk();
        $response->assertSee('New Medical Mission Record');
        $response->assertSee('name="image_with_id"', false);
        // The ID document itself is a second, separate upload.
        $response->assertSee('ID Proof');
        $response->assertSee('name="id_proof"', false);
        $response->assertDontSee('name="empanelment_error_image"', false);
        $response->assertDontSee('name="pcu_error_code"', false);
    }

    public function test_the_create_page_for_error_keeps_its_two_photos_and_no_id_proof(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.error.create'));

        $response->assertOk();
        $response->assertSee('name="image_with_id"', false);
        $response->assertSee('name="empanelment_error_image"', false);
        $response->assertSee('name="pcu_error_code"', false);
        $response->assertDontSee('name="id_proof"', false);
    }

    public function test_the_success_create_page_saves_without_a_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('records.success.create'));

        $response->assertOk();
        $response->assertSee('Save Record');
        $response->assertDontSee('name="template_id"', false);
        $response->assertDontSee('name="image_with_id"', false);
    }

    public function test_a_printing_type_cannot_be_added_before_its_template_is_uploaded(): void
    {
        $this->templates['mission']->update(['is_active' => false]);

        $response = $this->actingAs($this->user)->get(route('records.mission.create'));

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('error');
    }

    public function test_the_settings_page_offers_one_upload_per_printing_type(): void
    {
        $response = $this->actingAs($this->user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('name="template_file_error"', false);
        $response->assertSee('name="template_file_mission"', false);
        // Success prints nothing, so it gets no upload slot.
        $response->assertDontSee('name="template_file_success"', false);
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
     * A mission template that declares an error-image slot the record has no
     * value for: generation must skip that slot rather than demand it, while
     * still demanding the ID photo and the ID proof.
     */
    public function test_generation_demands_the_id_photos_but_not_an_error_image_for_a_mission(): void
    {
        $this->withFields('mission', ['image_with_id', 'id_proof', 'empanelment_error']);

        $mission = $this->makeRecord('mission');
        $mission->forceFill([
            'image_with_id_path' => $this->storeFakeIdPhoto('id'),
            'id_proof_image_path' => $this->storeFakeIdPhoto('proof'),
        ])->save();

        // Both required photos are supplied, so no error screenshot is demanded.
        $this->actingAs($this->user)
            ->from(route('records.show', $mission))
            ->get(route('records.generate', $mission))
            ->assertSessionHasNoErrors();

        // With the photos missing, the image slots are still reported.
        $noPhotos = $this->makeRecord('mission', 'NO PHOTOS');
        $this->actingAs($this->user)
            ->from(route('records.show', $noPhotos))
            ->get(route('records.generate', $noPhotos))
            ->assertSessionHasErrors(['generation']);

        $message = session('errors')->first('generation');
        $this->assertStringContainsString('ID Image', $message);
        $this->assertStringContainsString('ID Proof', $message);
        $this->assertStringNotContainsString('Empanelment Error Image', $message);
    }

    public function test_a_missing_id_proof_alone_blocks_generation(): void
    {
        $this->withFields('mission', ['image_with_id', 'id_proof']);

        $mission = $this->makeRecord('mission');
        $mission->forceFill(['image_with_id_path' => $this->storeFakeIdPhoto('id')])->save();

        $this->actingAs($this->user)
            ->from(route('records.show', $mission))
            ->get(route('records.generate', $mission))
            ->assertSessionHasErrors(['generation']);

        $message = session('errors')->first('generation');
        $this->assertStringContainsString('ID Proof', $message);
        $this->assertStringNotContainsString('ID Image', $message);
    }

    public function test_generation_still_demands_the_error_image_for_a_pcu_error(): void
    {
        $this->withFields('error', ['image_with_id', 'empanelment_error']);

        $error = $this->makeRecord('error');
        $error->forceFill(['image_with_id_path' => $this->storeFakeIdPhoto('id')])->save();

        $this->actingAs($this->user)
            ->from(route('records.show', $error))
            ->get(route('records.generate', $error))
            ->assertSessionHasErrors(['generation']);

        $this->assertStringContainsString(
            'Empanelment Error Image',
            session('errors')->first('generation')
        );
    }

    public function test_the_list_drops_the_head_of_clinic_and_type_columns(): void
    {
        $this->makeRecord('error', 'ERROR ROW');
        $this->makeRecord('mission', 'MISSION ROW');

        foreach ([route('records.error'), route('records.mission')] as $url) {
            $response = $this->actingAs($this->user)->get($url);

            $response->assertOk();
            $response->assertDontSee('Head of Clinic');
            $response->assertDontSee('DR. SANTOS', false);
            $response->assertDontSee('<th>Type</th>', false);
            // The row's own values must still be there.
            $response->assertSee('PhilHealth ID');
        }
    }

    public function test_a_data_only_record_can_never_be_generated(): void
    {
        $success = $this->makeRecord('success');

        $this->actingAs($this->user)
            ->get(route('records.generate', $success))
            ->assertForbidden();
    }

    public function test_a_data_only_record_can_never_be_printed_or_marked_printed(): void
    {
        $success = $this->makeRecord('success');

        $this->actingAs($this->user)
            ->get(route('records.print', $success))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->from(route('records.show', $success))
            ->post(route('records.mark-printed', $success))
            ->assertForbidden();

        $this->assertSame('generated', $success->fresh()->status, 'status must be untouched');
    }

    public function test_a_data_only_record_saves_to_the_table_without_generating(): void
    {
        $response = $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'success',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'pcu_error_code' => 'PCU-OK-1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('records.success'));

        $record = PatientRecord::where('patient_name', 'JUAN DELA CRUZ')->firstOrFail();
        $this->assertNull($record->template_id, 'no layout is attached');
        $this->assertNull($record->generated_file_path);
        $this->assertDatabaseCount('document_generations', 0);
    }

    public function test_a_record_cannot_be_pinned_to_another_types_template(): void
    {
        $this->actingAs($this->user)->post(route('records.store'), [
            'record_type' => 'mission',
            'patient_name' => 'WRONG FORM',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->templates['error']->id,
        ])->assertSessionHasErrors(['template_id']);

        $this->assertDatabaseMissing('patient_records', ['patient_name' => 'WRONG FORM']);
    }

    public function test_a_superseded_template_still_lets_its_own_record_be_edited(): void
    {
        $mission = $this->makeRecord('mission');
        $mission->forceFill(['patient_name' => 'BEFORE'])->save();

        // Adoption normally moves records off a retired template, but a rebuild
        // can fail; the stranded record must stay editable.
        $this->templates['mission']->update(['is_active' => false]);

        $this->actingAs($this->user)->put(route('records.update', $mission), [
            'record_type' => 'mission',
            'patient_name' => 'AFTER',
            'birthdate' => '1990-05-05',
            'philhealth_id' => '12-345678901-2',
            'template_id' => $this->templates['mission']->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('AFTER', $mission->fresh()->patient_name);
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
