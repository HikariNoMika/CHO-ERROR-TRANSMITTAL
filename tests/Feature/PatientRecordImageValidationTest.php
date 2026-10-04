<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The image slots on the edit form show the stored picture and are locked, so
 * saving without re-uploading must not be treated as "no image supplied" — and
 * must not delete what is already there.
 */
class PatientRecordImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function makeTemplate(): Template
    {
        $template = Template::create([
            'name' => 'Tmp',
            'file_path' => 'templates/tmp.xlsx',
            'created_by' => $this->user->id,
        ]);

        foreach ([
            ['person_fullname', 'text'],
            ['person_bdate', 'text'],
            ['person_with_id', 'image'],
            ['empanelment_error', 'image'],
        ] as [$placeholder, $type]) {
            $template->fields()->create([
                'placeholder' => $placeholder,
                'label' => $placeholder,
                'type' => $type,
                'is_required' => true,
            ]);
        }

        Storage::disk('private')->put('templates/tmp.xlsx', 'x');

        return $template;
    }

    protected function payload(Template $template, array $extra = []): array
    {
        return array_merge([
            'template_id' => $template->id,
            'record_type' => 'error',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            // The form always posts these hidden flags; "0" means keep.
            'remove_image_with_id' => '0',
            'remove_empanelment_error_image' => '0',
        ], $extra);
    }

    protected function makeRecord(Template $template, bool $withImages): PatientRecord
    {
        Storage::disk('private')->put('patient-images/id.png', 'x');
        Storage::disk('private')->put('patient-images/err.png', 'x');

        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $template->id,
            'record_type' => 'error',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'image_with_id_path' => $withImages ? 'patient-images/id.png' : null,
            'empanelment_error_image_path' => $withImages ? 'patient-images/err.png' : null,
        ]);
    }

    protected function errorKeys(): array
    {
        $bag = session('errors')?->getBag('default');

        return $bag ? $bag->keys() : [];
    }

    public function test_editing_keeps_images_that_were_already_uploaded(): void
    {
        Storage::fake('private');
        $template = $this->makeTemplate();
        $record = $this->makeRecord($template, true);

        $this->actingAs($this->user)
            ->put(route('records.update', $record), $this->payload($template));

        $errors = $this->errorKeys();
        $this->assertNotContains('image_with_id', $errors, 'ID image wrongly reported as missing');
        $this->assertNotContains('empanelment_error_image', $errors, 'Empanelment error image wrongly reported as missing');

        $record->refresh();
        $this->assertSame('patient-images/id.png', $record->image_with_id_path, 'Stored ID image was deleted on save');
        $this->assertSame('patient-images/err.png', $record->empanelment_error_image_path, 'Stored error image was deleted on save');
    }

    public function test_editing_a_record_that_has_no_images_still_requires_them(): void
    {
        Storage::fake('private');
        $template = $this->makeTemplate();
        $record = $this->makeRecord($template, false);

        $this->actingAs($this->user)
            ->put(route('records.update', $record), $this->payload($template));

        $errors = $this->errorKeys();
        $this->assertContains('image_with_id', $errors);
        $this->assertContains('empanelment_error_image', $errors);
    }

    public function test_removing_an_image_demands_a_replacement(): void
    {
        Storage::fake('private');
        $template = $this->makeTemplate();
        $record = $this->makeRecord($template, true);

        $this->actingAs($this->user)->put(
            route('records.update', $record),
            $this->payload($template, ['remove_image_with_id' => '1'])
        );

        $errors = $this->errorKeys();
        $this->assertContains('image_with_id', $errors, 'Removing the ID image should require a replacement');
        $this->assertNotContains('empanelment_error_image', $errors, 'The untouched image should not be flagged');
    }
}