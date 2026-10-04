<?php

namespace Tests\Feature;

use App\Models\DocumentGeneration;
use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\TemplateAdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-type templates: uploading a template must move that type's records onto
 * it and rebuild the documents that already exist, while leaving every other
 * type's records and layouts untouched.
 */
class TemplateAdoptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function makeTemplate(string $version, string $recordType = 'error'): Template
    {
        return Template::create([
            'name' => 'Form',
            'record_type' => $recordType,
            'file_path' => 'templates/'.$version.'.xlsx',
            'version' => $version,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);
    }

    protected function makeRecord(Template $template, string $status, ?string $file, string $type = 'error'): PatientRecord
    {
        return PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => $template->id,
            'record_type' => $type,
            'patient_name' => 'NAME '.$status,
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => $status,
            'generated_file_path' => $file,
        ]);
    }

    /**
     * Stand in for real generation, which always sets status to 'generated'.
     */
    protected function fakeGeneration(array &$seen): void
    {
        $this->mock(DocumentGenerationService::class)
            ->shouldReceive('generate')
            ->andReturnUsing(function (PatientRecord $record, User $user) use (&$seen) {
                $seen[] = $record->id;
                $record->update(['status' => 'generated', 'generated_file_path' => 'out/'.$record->id.'.xlsx']);

                return DocumentGeneration::create([
                    'patient_record_id' => $record->id,
                    'template_id' => $record->template_id,
                    'template_version' => 'x',
                    'file_path' => 'out/'.$record->id.'.xlsx',
                    'generated_by' => $user->id,
                ]);
            });
    }

    public function test_every_record_of_that_type_moves_to_the_new_template(): void
    {
        $oldA = $this->makeTemplate('1.0.0');
        $oldB = $this->makeTemplate('1.0.1');
        $new = $this->makeTemplate('1.0.2');

        $a = $this->makeRecord($oldA, 'generated', 'out/a.xlsx');
        $b = $this->makeRecord($oldB, 'printed', 'out/b.xlsx');
        $c = $this->makeRecord($oldA, 'draft', null);

        $seen = [];
        $this->fakeGeneration($seen);

        $result = app(TemplateAdoptionService::class)->adopt($new, $this->user);

        $this->assertSame(3, $result['migrated']);
        $this->assertSame([$a->id, $b->id], $seen, 'only records that already have a document are rebuilt');

        foreach ([$a, $b, $c] as $r) {
            $this->assertSame($new->id, $r->fresh()->template_id);
        }
    }

    public function test_records_of_another_type_are_left_alone(): void
    {
        $oldError = $this->makeTemplate('1.0.0', 'error');
        $oldMission = $this->makeTemplate('1.0.0', 'mission');
        $newError = $this->makeTemplate('1.0.1', 'error');

        $error = $this->makeRecord($oldError, 'generated', 'out/e.xlsx', 'error');
        $mission = $this->makeRecord($oldMission, 'generated', 'out/m.xlsx', 'mission');

        $seen = [];
        $this->fakeGeneration($seen);

        $result = app(TemplateAdoptionService::class)->adopt($newError, $this->user);

        $this->assertSame(1, $result['migrated']);
        $this->assertSame([$error->id], $seen, 'the mission document must not be rebuilt');
        $this->assertSame($newError->id, $error->fresh()->template_id);
        $this->assertSame($oldMission->id, $mission->fresh()->template_id);
    }

    public function test_data_only_records_are_never_migrated(): void
    {
        $new = $this->makeTemplate('1.0.1', 'error');

        $success = PatientRecord::create([
            'created_by' => $this->user->id,
            'template_id' => null,
            'record_type' => 'success',
            'patient_name' => 'NO TEMPLATE',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'status' => 'generated',
            'generated_file_path' => 'out/s.xlsx',
        ]);

        $seen = [];
        $this->fakeGeneration($seen);

        $result = app(TemplateAdoptionService::class)->adopt($new, $this->user);

        $this->assertSame(0, $result['migrated']);
        $this->assertSame([], $seen);
        $this->assertNull($success->fresh()->template_id);
    }

    public function test_printed_records_stay_printed(): void
    {
        $old = $this->makeTemplate('1.0.0');
        $new = $this->makeTemplate('1.0.1');

        $printed = $this->makeRecord($old, 'printed', 'out/p.xlsx');
        $generated = $this->makeRecord($old, 'generated', 'out/g.xlsx');

        $seen = [];
        $this->fakeGeneration($seen);

        app(TemplateAdoptionService::class)->adopt($new, $this->user);

        $this->assertSame('printed', $printed->fresh()->status, 'printed must not be downgraded');
        $this->assertSame('generated', $generated->fresh()->status);
    }

    public function test_past_generations_keep_pointing_at_the_old_template(): void
    {
        $old = $this->makeTemplate('1.0.0');
        $new = $this->makeTemplate('1.0.1');
        $rec = $this->makeRecord($old, 'generated', 'out/r.xlsx');

        $history = DocumentGeneration::create([
            'patient_record_id' => $rec->id,
            'template_id' => $old->id,
            'template_version' => '1.0.0',
            'file_path' => 'out/r.xlsx',
            'generated_by' => $this->user->id,
        ]);

        $seen = [];
        $this->fakeGeneration($seen);

        app(TemplateAdoptionService::class)->adopt($new, $this->user);

        $history->refresh();
        $this->assertSame($old->id, $history->template_id, 'history is append-only and must not be rewritten');
        $this->assertSame('1.0.0', $history->template_version);
    }

    public function test_one_bad_record_does_not_abandon_the_rest(): void
    {
        $old = $this->makeTemplate('1.0.0');
        $new = $this->makeTemplate('1.0.1');
        $bad = $this->makeRecord($old, 'generated', 'out/bad.xlsx');
        $good = $this->makeRecord($old, 'generated', 'out/good.xlsx');

        $this->mock(DocumentGenerationService::class)
            ->shouldReceive('generate')
            ->andReturnUsing(function (PatientRecord $record, User $user) use ($bad) {
                if ($record->id === $bad->id) {
                    throw new \RuntimeException('boom');
                }
                $record->update(['status' => 'generated']);

                return DocumentGeneration::create([
                    'patient_record_id' => $record->id,
                    'template_id' => $record->template_id,
                    'template_version' => 'x',
                    'file_path' => 'out/'.$record->id.'.xlsx',
                    'generated_by' => $user->id,
                ]);
            });

        $result = app(TemplateAdoptionService::class)->adopt($new, $this->user);

        $this->assertSame(1, $result['rebuilt']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame($new->id, $bad->fresh()->template_id, 'a failure must not stop the migration');
        $this->assertSame($new->id, $good->fresh()->template_id);
    }

    public function test_summary_reads_plainly(): void
    {
        $service = app(TemplateAdoptionService::class);

        $this->assertSame(
            '3 records now use this template. 2 documents rebuilt.',
            $service->summarise(['migrated' => 3, 'existing' => 2, 'rebuilt' => 2, 'failed' => []])
        );

        $this->assertSame(
            'No existing records needed updating.',
            $service->summarise(['migrated' => 0, 'existing' => 0, 'rebuilt' => 0, 'failed' => []])
        );

        $this->assertStringContainsString(
            'could not be rebuilt',
            $service->summarise(['migrated' => 1, 'existing' => 1, 'rebuilt' => 0, 'failed' => ['JUAN']])
        );
    }
}
