<?php

namespace App\Services;

/**
 * Canonical mapping between Excel placeholder names and patient record fields.
 *
 * Templates in the wild use different placeholder vocabularies
 * (e.g. {{person_fullname}} vs {{patient_name}}). This map normalises
 * them so generation, validation and template analysis stay consistent
 * without hard-coding a single template's vocabulary.
 */
class PlaceholderMap
{
    /** placeholder => canonical text field */
    const TEXT = [
        // patient name
        'patient_name' => 'patient_name',
        'fullname' => 'patient_name',
        'full_name' => 'patient_name',
        'person_fullname' => 'patient_name',
        'name' => 'patient_name',
        // birthdate
        'birthdate' => 'birthdate',
        'person_bdate' => 'birthdate',
        'bdate' => 'birthdate',
        // philhealth id
        'philhealth_id' => 'philhealth_id',
        'person_philid' => 'philhealth_id',
        'philid' => 'philhealth_id',
        // head of clinic
        'head_of_clinic' => 'head_of_clinic',
        'office_head' => 'head_of_clinic',
        // generation date
        'date_today' => 'date_today',
        // facility
        'facility_name' => 'facility_name',
        'clinic_name' => 'facility_name',
        'health_care_institution' => 'facility_name',
        'facility_address' => 'facility_address',
        'institution_address' => 'facility_address',
        // ATC-slip fields
        'beneficiary_name' => 'patient_name',
        'appointment_date' => 'appointment_date',
        'date_of_appointment' => 'appointment_date',
        'auth_transaction_code' => 'auth_transaction_code',
        'auth_code' => 'auth_transaction_code',
        'atc' => 'auth_transaction_code',
        'atc_code' => 'auth_transaction_code',
        // PCU error code
        'pcu_error_code' => 'pcu_error_code',
        'pcu_code' => 'pcu_error_code',
        'pcu_error' => 'pcu_error_code',
    ];

    /** placeholder => image slot ('id' | 'error') */
    const IMAGES = [
        'image_with_id' => 'id',
        'person_with_id' => 'id',
        'id_image' => 'id',
        'photo' => 'id',
        'empanelment_error' => 'error',
        'empanelment_error_image' => 'error',
        'error_image' => 'error',
    ];

    /** Canonical text fields that are required by default on new templates. */
    const REQUIRED_TEXT_FIELDS = ['patient_name', 'birthdate', 'philhealth_id', 'head_of_clinic'];

    public static function canonicalText(string $placeholder): ?string
    {
        return self::TEXT[strtolower($placeholder)] ?? null;
    }

    public static function imageSlot(string $placeholder): ?string
    {
        return self::IMAGES[strtolower($placeholder)] ?? null;
    }

    public static function type(string $placeholder): string
    {
        if (self::imageSlot($placeholder) !== null) {
            return 'image';
        }
        $canonical = self::canonicalText($placeholder);
        if (in_array($canonical, ['birthdate', 'date_today', 'appointment_date'], true)) {
            return 'date';
        }
        return 'text';
    }

    public static function isKnown(string $placeholder): bool
    {
        return self::canonicalText($placeholder) !== null || self::imageSlot($placeholder) !== null;
    }
}
