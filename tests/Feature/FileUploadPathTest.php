<?php

namespace Tests\Feature;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use App\Services\PlaceholderMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FileUploadPathTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->user = User::factory()->create();
        $this->user->forceFill(['is_active' => true, 'role' => 'admin'])->save();
    }

    protected function makeTemplate(string $type = 'error'): Template
    {
        $path = 'templates/'.$type.'.xlsx';

        Storage::disk('private')->makeDirectory('templates');
        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('A1', 'x');
        (new Xlsx($book))->save(Storage::disk('private')->path($path));
        unset($book);

        $template = Template::create([
            'name' => ucfirst($type).' form',
            'record_type' => $type,
            'file_path' => $path,
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        foreach (['image_with_id', 'empanelment_error'] as $i => $placeholder) {
            $template->fields()->create([
                'placeholder' => $placeholder,
                'label' => $placeholder,
                'type' => PlaceholderMap::type($placeholder),
                'is_required' => true,
                'sort_order' => $i + 1,
            ]);
        }

        return $template;
    }

    protected function basePayload(Template $template): array
    {
        return [
            'template_id' => $template->id,
            'record_type' => 'error',
            'patient_name' => 'JUAN DELA CRUZ',
            'birthdate' => '2000-01-15',
            'philhealth_id' => '12-345678901-2',
            'head_of_clinic' => 'DR. SANTOS',
            'remove_image_with_id' => '0',
            'remove_empanelment_error_image' => '0',
        ];
    }

    public function test_a_file_upload_is_stored(): void
    {
        $template = $this->makeTemplate();

        $this->actingAs($this->user)->post(route('records.store'), $this->basePayload($template) + [
            'image_with_id' => UploadedFile::fake()->image('id.jpg', 20, 20),
            'empanelment_error_image' => UploadedFile::fake()->image('err.png', 20, 20),
        ]);

        $record = PatientRecord::latest('id')->first();
        $this->assertNotNull($record->image_with_id_path);
        $this->assertNotNull($record->empanelment_error_image_path);
    }

    public function test_a_file_upload_record_can_be_generated(): void
    {
        $template = $this->makeTemplate();

        $this->actingAs($this->user)->post(route('records.store'), $this->basePayload($template) + [
            'image_with_id' => UploadedFile::fake()->image('id.jpg', 20, 20),
            'empanelment_error_image' => UploadedFile::fake()->image('err.png', 20, 20),
        ]);

        $record = PatientRecord::latest('id')->first();

        $gen = $this->actingAs($this->user)->get(route('records.generate', $record));

        $errors = session('errors')?->getBag('default')->all();
        $record->refresh();

        $this->assertSame('generated', $record->status, 'record stayed draft after file upload; generation errors: '.json_encode($errors));
    }

    public function test_a_paste_record_can_be_generated(): void
    {
        $template = $this->makeTemplate();

        $png = base64_encode(UploadedFile::fake()->image('p.png', 20, 20)->getContent());

        $this->actingAs($this->user)->post(route('records.store'), $this->basePayload($template) + [
            'image_with_id_base64' => 'data:image/png;base64,'.$png,
            'empanelment_error_image_base64' => 'data:image/png;base64,'.$png,
        ]);

        $record = PatientRecord::latest('id')->first();

        $this->actingAs($this->user)->get(route('records.generate', $record));

        $errors = session('errors')?->getBag('default')->all();
        $record->refresh();

        $this->assertSame('generated', $record->status, 'record stayed draft after paste; generation errors: '.json_encode($errors));
    }
}
