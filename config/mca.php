<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Text autofit
    |--------------------------------------------------------------------------
    |
    | Long values (patient names, facility names) are often wider than the box
    | they sit in, and Excel clips them rather than overflowing. These settings
    | shrink the font just enough for the value to stay readable inside the box.
    | Nothing here resizes or moves the template's shapes.
    |
    | Shapes are scaled by injecting DrawingML's own autofit, so Excel keeps
    | treating the box as a fixed size. Cells use the native "shrink to fit"
    | alignment, which Excel recalculates itself.
    |
    */

    'autofit' => [

        // Master switch. Turn off to generate documents at the template's
        // original font sizes.
        'enabled' => env('MCA_AUTOFIT_ENABLED', true),

        /*
         | Canonical placeholder names this applies to. Use the canonical names
         | from app/Services/PlaceholderMap.php, not template aliases, so every
         | alias of a field is covered:
         |
         |   patient_name, birthdate, date_today, philhealth_id, head_of_clinic,
         |   facility_name, facility_address, appointment_date,
         |   auth_transaction_code, pcu_error_code
         |
         | Use ['*'] to apply to every text placeholder.
         */
        'fields' => ['patient_name'],

        // Never shrink below this point size, in points. Anything smaller is
        // unreadable on a printed form.
        'min_font_pt' => 8.0,

        /*
         | How far wider than the box the text must be before we intervene.
         | Measurement is approximate, so a little slack avoids shrinking text
         | that would actually have fitted.
         */
        'tolerance' => 1.02,

        /*
         | Multiplier applied to the measured width. Arial metrics are used,
         | which are wider than Calibri. Raise this if real output still clips
         | with a wider font; lower it if text is being shrunk unnecessarily.
         */
        'width_factor' => 1.0,

        /*
         | Record a warning on the generated document whenever a value is
         | condensed, so it is visible that the template's font size was reduced.
         */
        'warn' => true,
    ],

];