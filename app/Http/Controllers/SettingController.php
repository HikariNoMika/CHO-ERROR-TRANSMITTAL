<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Models\Template;
use App\Services\AuditLogService;
use App\Services\TemplateAdoptionService;
use App\Services\TemplateFieldService;
use App\Services\TemplateParserService;
use App\Support\RecordType;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct(
        protected TemplateParserService $parser,
        protected TemplateFieldService $fieldService,
        protected TemplateAdoptionService $adoption,
        protected AuditLogService $auditLog
    ) {}

    // Route already protected by role:admin middleware.
    public function index()
    {
        // Each printing type has its own live template, so the page lists them
        // all rather than a single "current" one.
        $templates = [];
        $fields = [];

        foreach (RecordType::templateTypes() as $type) {
            $template = Template::activeFor($type);
            $templates[$type] = $template;

            // Paginated: a template with many detected placeholders should not
            // dump an unbounded table onto the settings page. One page name per
            // type so the paginators do not fight over the query string.
            $fields[$type] = $template
                ? $template->fields()->orderBy('sort_order')->paginate(12, ['*'], 'fields_'.$type)
                : null;
        }

        return view('settings.index', compact('templates', 'fields'));
    }

    public function update(SettingRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            if (str_starts_with($key, 'template_file')) {
                continue;
            }
            Setting::set($key, $value);
        }

        $templateSummary = null;

        foreach (RecordType::templateTypes() as $type) {
            $key = 'template_file_'.$type;

            if ($request->hasFile($key)) {
                $templateSummary = $this->replaceTemplate($request, $type);
            }
        }

        return back()->with('success', $templateSummary ?? 'Settings updated successfully.');
    }

    /**
     * Per-type template: the uploaded file becomes the live template for that
     * record type (previous version bumped), the type's other templates are
     * deactivated, and that type's existing records are moved onto it.
     *
     * @return string summary of what the swap changed
     */
    protected function replaceTemplate(SettingRequest $request, string $type): string
    {
        $previous = Template::activeFor($type);

        $file = $request->file('template_file_'.$type);
        $path = $file->storeAs('templates', $file->hashName(), 'private');

        $detected = $this->parser->parse(Storage::disk('private')->path($path));

        $template = Template::create([
            'name' => $previous?->name ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'record_type' => $type,
            'description' => $previous?->description,
            'file_path' => $path,
            'version' => $this->bumpVersion($previous?->version),
            'is_active' => true,
            'detected_placeholders' => $detected,
            'created_by' => $request->user()->id,
        ]);

        $this->fieldService->sync($template, $detected);

        // Only this type's templates are deactivated; the other type keeps its
        // own live layout.
        Template::where('record_type', $type)
            ->where('id', '!=', $template->id)
            ->update(['is_active' => false]);

        // One live template per type, so that type's records follow the new file.
        $result = $this->adoption->adopt($template, $request->user());

        $this->auditLog->logTemplateUploaded($template, $request);

        return RecordType::label($type).' template v'.$template->version.' is now active. '
            .$this->adoption->summarise($result);
    }

    protected function bumpVersion(?string $version): string
    {
        if (! $version || ! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) {
            return '1.0.0';
        }

        return $m[1].'.'.$m[2].'.'.((int) $m[3] + 1);
    }
}
