<?php

namespace App\Http\Requests;

use App\Support\RecordType;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'head_of_clinic' => 'nullable|string|max:255',
            'facility_name' => 'nullable|string|max:255',
            'facility_address' => 'nullable|string|max:500',
        ];

        // One upload slot per printing type, so PCU Error and Medical Mission
        // keep independent layouts.
        foreach (RecordType::templateTypes() as $type) {
            $rules['template_file_'.$type] = 'nullable|file|mimes:xlsx|max:10240';
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];

        foreach (RecordType::templateTypes() as $type) {
            $attributes['template_file_'.$type] = RecordType::label($type).' template file';
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'head_of_clinic.max' => 'Head of clinic name is too long.',
            'facility_name.max' => 'Facility name is too long.',
            'template_file_*.mimes' => 'The :attribute must be an .xlsx file.',
        ];
    }
}
