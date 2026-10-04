<?php

namespace App\Http\Requests;

use App\Models\PatientRecord;
use App\Models\Setting;
use App\Models\Template;
use App\Services\PlaceholderMap;
use App\Support\RecordType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PatientRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single-flow validation: every save that prints generates the document, so
     * the template's required fields must be complete up front. Data-only
     * records have no template and are validated by the base rules alone.
     *
     * What each type insists on lives in App\Support\RecordType: success
     * records are data-only quick logs, and medical mission records register a
     * patient on an ID photo plus a photo of the ID document itself.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = RecordType::normalise($this->input('record_type'));

            // Data-only records print nothing, so there is no layout to satisfy.
            if (! RecordType::usesTemplate($type)) {
                return;
            }

            $template = Template::find($this->input('template_id'));
            if (! $template) {
                return;
            }
            $needsEvidence = RecordType::needsEvidence($type);
            $required = $template->fields()->where('is_required', true)->pluck('placeholder');
            foreach ($required as $field) {
                $canonical = PlaceholderMap::canonicalText($field);
                if ($canonical === null || in_array($canonical, ['patient_name', 'birthdate', 'philhealth_id'], true)) {
                    continue; // covered by the base rules above
                }
                if ($canonical === 'head_of_clinic' && Setting::getHeadOfClinic() !== '') {
                    continue; // auto-filled from settings at store time
                }
                if (! $this->filled($canonical)) {
                    $validator->errors()->add(
                        $canonical,
                        ucfirst(str_replace('_', ' ', $canonical)).' is required to generate this template.'
                    );
                }
            }
            $needsId = $required->contains(fn ($p) => PlaceholderMap::imageSlot($p) === 'id');
            $needsError = $required->contains(fn ($p) => PlaceholderMap::imageSlot($p) === 'error');
            $needsProof = $required->contains(fn ($p) => PlaceholderMap::imageSlot($p) === 'id_proof');

            // On edit the image slots show the already-stored picture and are
            // locked, so no new file is posted. Count that as provided unless the
            // user ticked the box to remove it.
            $record = $this->route('record');
            if (! ($record instanceof PatientRecord)) {
                $record = null;
            }

            $provided = function (string $file, string $base64, ?string $stored, string $remove) use ($record) {
                return $this->hasFile($file)
                    || $this->filled($base64)
                    || ($record && $stored && ! $this->boolean($remove));
            };

            $idProvided = $provided('image_with_id', 'image_with_id_base64', $record?->image_with_id_path, 'remove_image_with_id');
            $errorProvided = $provided('empanelment_error_image', 'empanelment_error_image_base64', $record?->empanelment_error_image_path, 'remove_empanelment_error_image');
            $proofProvided = $provided('id_proof', 'id_proof_base64', $record?->id_proof_image_path, 'remove_id_proof');

            if ($needsEvidence && $needsId && ! $idProvided) {
                $validator->errors()->add('image_with_id', 'ID image is required to generate this template.');
            }
            if ($needsEvidence && RecordType::needsErrorImage($type) && $needsError && ! $errorProvided) {
                $validator->errors()->add('empanelment_error_image', 'Empanelment error image is required to generate this template.');
            }
            if ($needsEvidence && RecordType::needsIdProof($type) && $needsProof && ! $proofProvided) {
                $validator->errors()->add('id_proof', 'ID proof is required to generate this template.');
            }
        });
    }

    /**
     * PhilHealth issues PINs as 12 digits in 2-9-1 format (e.g. 12-345678901-2).
     * Accept the digits with or without separators and store the canonical form.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('philhealth_id')) {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) $this->input('philhealth_id'));

        $this->merge([
            'philhealth_id' => strlen($digits) === 12
                ? substr($digits, 0, 2).'-'.substr($digits, 2, 9).'-'.substr($digits, 11)
                : trim((string) $this->input('philhealth_id')),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_name' => 'required|string|max:255',
            'birthdate' => 'required|date|before_or_equal:today',
            'philhealth_id' => ['required', 'regex:/^\d{2}-\d{9}-\d$/', 'max:100'],
            'appointment_date' => 'nullable|date',
            'auth_transaction_code' => 'nullable|string|max:100',
            'pcu_error_code' => 'nullable|string|max:100|required_if:record_type,success',
            'record_type' => ['required', Rule::in(RecordType::slugs())],
            'head_of_clinic' => 'nullable|string|max:255',
            // Data-only records have no layout to point at.
            'template_id' => RecordType::usesTemplate($this->input('record_type'))
                ? ['required', $this->templateRule()]
                : 'nullable|exists:templates,id',
            'image_with_id' => 'nullable|image|max:10240|mimes:jpeg,jpg,png,webp',
            'empanelment_error_image' => 'nullable|image|max:10240|mimes:jpeg,jpg,png,webp',
            'id_proof' => 'nullable|image|max:10240|mimes:jpeg,jpg,png,webp',
            'image_with_id_base64' => 'nullable|string',
            'empanelment_error_image_base64' => 'nullable|string',
            'id_proof_base64' => 'nullable|string',
            'remove_image_with_id' => 'nullable|boolean',
            'remove_empanelment_error_image' => 'nullable|boolean',
            'remove_id_proof' => 'nullable|boolean',
        ];
    }

    /**
     * A printing record may only be attached to a layout built for its own type,
     * so a tampered or stale form cannot point a mission at the PCU error form.
     *
     * The record's current template also stays acceptable even when it is no
     * longer active: adoption rebuilds documents best-effort, so a record left
     * behind by a failed rebuild must still be editable.
     */
    protected function templateRule(): Exists
    {
        $type = RecordType::normalise($this->input('record_type'));

        return Rule::exists('templates', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q
                ->where('record_type', $type)
                ->where(function ($q) {
                    $q->where('is_active', true);

                    $current = $this->route('record');
                    if ($current instanceof PatientRecord && $current->template_id) {
                        $q->orWhere('id', $current->template_id);
                    }
                }));
    }

    public function messages(): array
    {
        return [
            'patient_name.required' => 'Patient name is required.',
            'birthdate.required' => 'Birthdate is required.',
            'birthdate.before_or_equal' => 'Birthdate cannot be in the future.',
            'philhealth_id.required' => 'PhilHealth ID is required.',
            'philhealth_id.regex' => 'PhilHealth ID must be a valid 12-digit PIN in 2-9-1 format (e.g. 12-345678901-2).',
            'pcu_error_code.required_if' => 'PCU Success Code is required for success records.',
            'template_id.required' => 'Please select a template.',
            'template_id.exists' => 'Selected template is not the active template for this record type.',
            'image_with_id.image' => 'ID Image must be a valid image file.',
            'image_with_id.max' => 'ID Image must not exceed 10MB.',
            'empanelment_error_image.image' => 'Empanelment Error Image must be a valid image file.',
            'empanelment_error_image.max' => 'Empanelment Error Image must not exceed 10MB.',
            'id_proof.image' => 'ID Proof must be a valid image file.',
            'id_proof.max' => 'ID Proof must not exceed 10MB.',
        ];
    }
}
