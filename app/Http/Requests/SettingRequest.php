<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head_of_clinic' => 'nullable|string|max:255',
            'facility_name' => 'nullable|string|max:255',
            'facility_address' => 'nullable|string|max:500',
            'template_file' => 'nullable|file|mimes:xlsx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'head_of_clinic.max' => 'Head of clinic name is too long.',
            'facility_name.max' => 'Facility name is too long.',
        ];
    }
}