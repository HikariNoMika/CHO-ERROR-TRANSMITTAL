<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateField;

/**
 * Keeps a template's configurable fields in sync with the placeholders
 * actually detected in its Excel file.
 */
class TemplateFieldService
{
    public function sync(Template $template, array $placeholders): void
    {
        $newPlaceholders = array_values(array_unique(array_column($placeholders, 'placeholder')));

        // Delete fields that are no longer in the template.
        $template->fields()->whereNotIn('placeholder', $newPlaceholders)->delete();

        // Create or update fields.
        foreach ($placeholders as $ph) {
            $placeholder = $ph['placeholder'];
            $type = $ph['type'] ?? PlaceholderMap::type($placeholder);
            $canonical = PlaceholderMap::canonicalText($placeholder);

            TemplateField::updateOrCreate(
                ['template_id' => $template->id, 'placeholder' => $placeholder],
                [
                    'label' => ucfirst(str_replace('_', ' ', $placeholder)),
                    'type' => $type,
                    // Core patient fields (by canonical name) and image
                    // slots are required: generation cannot succeed without them.
                    'is_required' => ($canonical !== null && in_array($canonical, PlaceholderMap::REQUIRED_TEXT_FIELDS, true))
                        || $type === 'image',
                    'validation_rules' => $this->validationRules($placeholder),
                    'sort_order' => array_search($placeholder, $newPlaceholders) + 1,
                ]
            );
        }
    }

    public function validationRules(string $placeholder): string
    {
        $canonical = PlaceholderMap::canonicalText($placeholder);
        if (PlaceholderMap::imageSlot($placeholder) !== null) {
            return 'nullable|image|max:10240';
        }
        $rules = [
            'patient_name' => 'required|string|max:255',
            'birthdate' => 'required|date',
            'philhealth_id' => 'required|string|max:100',
            'head_of_clinic' => 'required|string|max:255',
            'date_today' => 'nullable|date',
            'facility_name' => 'nullable|string|max:255',
            'facility_address' => 'nullable|string|max:500',
            'appointment_date' => 'nullable|date',
            'auth_transaction_code' => 'nullable|string|max:100',
            'pcu_error_code' => 'nullable|string|max:100',
        ];

        return $rules[$canonical] ?? 'nullable|string|max:255';
    }
}
