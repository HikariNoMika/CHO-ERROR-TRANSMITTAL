<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Template;
use App\Http\Requests\SettingRequest;
use App\Services\AuditLogService;
use App\Services\TemplateFieldService;
use App\Services\TemplateParserService;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct(
        protected TemplateParserService $parser,
        protected TemplateFieldService $fieldService,
        protected AuditLogService $auditLog
    ) {
    }

    // Route already protected by role:admin middleware.
    public function index()
    {
        $currentTemplate = Template::where('is_active', true)->orderBy('id')->first();

        // Paginated: a template with many detected placeholders should not
        // dump an unbounded table onto the settings page.
        $fields = $currentTemplate
            ? $currentTemplate->fields()->orderBy('sort_order')->paginate(12)
            : null;

        return view('settings.index', compact('currentTemplate', 'fields'));
    }

    public function update(SettingRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            if ($key === 'template_file') {
                continue;
            }
            Setting::set($key, $value);
        }

        if ($request->hasFile('template_file')) {
            $this->replaceTemplate($request);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Single-template mode: the uploaded file becomes the active template
     * (previous version bumped) and every other template is deactivated.
     */
    protected function replaceTemplate(SettingRequest $request): void
    {
        $previous = Template::where('is_active', true)->orderBy('id')->first();

        $file = $request->file('template_file');
        $path = $file->storeAs('templates', $file->hashName(), 'private');

        $detected = $this->parser->parse(Storage::disk('private')->path($path));

        $template = Template::create([
            'name' => $previous?->name ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $previous?->description,
            'file_path' => $path,
            'version' => $this->bumpVersion($previous?->version),
            'is_active' => true,
            'detected_placeholders' => $detected,
            'created_by' => $request->user()->id,
        ]);

        $this->fieldService->sync($template, $detected);

        Template::where('id', '!=', $template->id)->update(['is_active' => false]);

        $this->auditLog->logTemplateUploaded($template, $request);
    }

    protected function bumpVersion(?string $version): string
    {
        if (!$version || !preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) {
            return '1.0.0';
        }
        return $m[1] . '.' . $m[2] . '.' . ((int) $m[3] + 1);
    }
}
