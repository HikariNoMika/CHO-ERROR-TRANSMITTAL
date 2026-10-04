<?php

namespace App\Http\Requests;

use App\Models\Template;
use App\Services\PlaceholderMap;
use Illuminate\Foundation\Http\FormRequest;

class PatientRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single-flow validation: every save generates the document, so the
     * template's required fields must be complete up front.
     *
     * Success records are quick logs (no evidence images required and
     * birthdate may be unknown), so enforcement is relaxed for them.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $template = Template::find($this->input('template_id'));
            if (!$template) {
                return;
            }
            $isSuccess = $this->input('record_type') === 'success';
            $required = $template->fields()->where('is_required', true)->pluck('placeholder');
            foreach ($required as $field) {
                $canonical = PlaceholderMap::canonicalText($field);
                if ($canonical === null || in_array($canonical, ['patient_name', 'birthdate', 'philhealth_id'], true)) {
                    continue; // covered by the base rules above
                }
                if ($canonical === 'head_of_clinic' && \App\Models\Setting::getHeadOfClinic() !== '') {
                    continue; // auto-filled from settings at store time
                }
                if (!$this->filled($canonical)) {
                    $validator->errors()->add(
                        $canonical,
                        ucfirst(str_replace('_', ' ', $canonical)) . ' is required to generate this template.'
                    );
                }
            }
            $needsId = $required->contains(fn ($p) => PlaceholderMap::imageSlot($p) === 'id');
            $needsError = $required->contains(fn ($p) => PlaceholderMap::imageSlot($p) === 'error');

            if (!$isSuccess && $needsId && !$this->hasFile('image_with_id') && !$this->filled('image_with_id_base64')) {
                $validator->errors()->add('image_with_id', 'ID image is required to generate this template.');
            }
            if (!$isSuccess && $needsError && !$this->hasFile('empanelment_error_image') && !$this->filled('empanelment_error_image_base64')) {
                $validator->errors()->add('empanelment_error_image', 'Empanelment error image is required to generate this template.');
            }
        });
    }

    /**
     * PhilHealth issues PINs as 12 digits in 2-9-1 format (e.g. 12-345678901-2).
     * Accept the digits with or without separators and store the canonical form.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('philhealth_id')) {
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
            'record_type' => 'required|in:error,success',
            'head_of_clinic' => 'nullable|string|max:255',
            'template_id' => 'required|exists:templates,id',
            'image_with_id' => 'nullable|image|max:10240|mimes:jpeg,jpg,png,webp',
            'empanelment_error_image' => 'nullable|image|max:10240|mimes:jpeg,jpg,png,webp',
            'image_with_id_base64' => 'nullable|string',
            'empanelment_error_image_base64' => 'nullable|string',
            'remove_image_with_id' => 'nullable|boolean',
            'remove_empanelment_error_image' => 'nullable|boolean',
        ];
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
            'template_id.exists' => 'Selected template does not exist.',
            'image_with_id.image' => 'ID Image must be a valid image file.',
            'image_with_id.max' => 'ID Image must not exceed 10MB.',
            'empanelment_error_image.image' => 'Empanelment Error Image must be a valid image file.',
            'empanelment_error_image.max' => 'Empanelment Error Image must not exceed 10MB.',
        ];
    }
}