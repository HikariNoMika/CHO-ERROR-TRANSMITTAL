<?php

namespace App\Support;

/**
 * The kinds of patient record the app keeps.
 *
 * Each type owns a list, a create link and its labels. The per-type differences
 * live here rather than as `=== 'success'` checks scattered through the
 * controllers and views, so adding or adjusting a type is a matter of editing
 * one row.
 *
 * Two kinds of type exist:
 *  - printing types own an Excel template and produce a document
 *  - data-only types just record inputs and are exported
 *
 * Adding a type: add an entry to self::TYPES and the two matching routes.
 */
final class RecordType
{
    public const ERROR = 'error';

    public const SUCCESS = 'success';

    public const MISSION = 'mission';

    /** Fallback for rows saved before record_type existed. */
    public const DEFAULT = self::ERROR;

    /**
     * slug => [
     *   label          name on buttons, badges and headings
     *   plural         name of the list page
     *   badge          css class for the type badge in the table
     *   route          named route of the list
     *   create_route   named route of the new-record form
     *   path           url segment
     *   template       whether the type prints from its own Excel template
     *   evidence       whether the template's image slots must be filled
     *   error_code     whether the type's own code field is mandatory
     *   error_image    whether the empanelment-error photo is mandatory
     *   id_proof       whether a photo of the ID document itself is mandatory
     * ]
     *
     * @var array<string, array<string, bool|string>>
     */
    private const TYPES = [
        self::ERROR => [
            'label' => 'PCU Error',
            'plural' => 'PCU Error Records',
            'badge' => 'badge-red',
            'route' => 'records.error',
            'create_route' => 'records.error.create',
            'path' => 'error',
            'template' => true,
            'evidence' => true,
            'error_code' => false,
            'error_image' => true,
            'id_proof' => false,
        ],
        self::SUCCESS => [
            'label' => 'PCU Success',
            'plural' => 'PCU Success Records',
            'badge' => 'badge-green',
            'route' => 'records.success',
            'create_route' => 'records.success.create',
            'path' => 'success',
            // A quick log: the inputs are the record, and they are exported.
            // Nothing is printed, so there is no template to fill or keep.
            'template' => false,
            'evidence' => false,
            'error_code' => true,
            'error_image' => false,
            'id_proof' => false,
        ],
        self::MISSION => [
            'label' => 'Medical Mission',
            'plural' => 'Medical Mission Records',
            'badge' => 'badge-blue',
            'route' => 'records.mission',
            'create_route' => 'records.mission.create',
            'path' => 'mission',
            // Registers a patient on a mission: the ID photo and the ID
            // document are the evidence. The PCU error screenshot, which the
            // error template carries, has no meaning here.
            'template' => true,
            'evidence' => true,
            'error_code' => false,
            'error_image' => false,
            'id_proof' => true,
        ],
    ];

    /** @return string[] */
    public static function slugs(): array
    {
        return array_keys(self::TYPES);
    }

    public static function exists(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    /** Normalises anything (missing, legacy, hand-typed) to a known slug. */
    public static function normalise(?string $type): string
    {
        return self::exists($type) ? $type : self::DEFAULT;
    }

    /** @return array<string, bool|string> */
    public static function definition(?string $type): array
    {
        return self::TYPES[self::normalise($type)];
    }

    private static function get(?string $type, string $key): string
    {
        return (string) self::definition($type)[$key];
    }

    private static function flag(?string $type, string $key): bool
    {
        return (bool) self::definition($type)[$key];
    }

    public static function label(?string $type): string
    {
        return self::get($type, 'label');
    }

    public static function plural(?string $type): string
    {
        return self::get($type, 'plural');
    }

    public static function badge(?string $type): string
    {
        return self::get($type, 'badge');
    }

    /**
     * Whether this type prints from its own Excel template. Data-only types
     * record inputs for export and have no template at all.
     */
    public static function usesTemplate(?string $type): bool
    {
        return self::flag($type, 'template');
    }

    /** Whether the template's image slots must be supplied before saving. */
    public static function needsEvidence(?string $type): bool
    {
        return self::flag($type, 'evidence');
    }

    public static function needsErrorCode(?string $type): bool
    {
        return self::flag($type, 'error_code');
    }

    public static function needsErrorImage(?string $type): bool
    {
        return self::needsEvidence($type) && self::flag($type, 'error_image');
    }

    /** Whether a photo of the ID document itself is required. */
    public static function needsIdProof(?string $type): bool
    {
        return self::needsEvidence($type) && self::flag($type, 'id_proof');
    }

    /** @return string[] types that own an Excel template */
    public static function templateTypes(): array
    {
        return array_values(array_filter(
            self::slugs(),
            fn (string $slug): bool => self::usesTemplate($slug)
        ));
    }

    public static function indexRoute(?string $type): string
    {
        return self::get($type, 'route');
    }

    public static function createRoute(?string $type): string
    {
        return self::get($type, 'create_route');
    }

    /** URL of the list for a type, keeping whatever filters are on screen. */
    public static function indexUrl(?string $type, array $query = []): string
    {
        $query['type'] = self::normalise($type);

        return route(self::indexRoute($type), $query);
    }

    public static function createUrl(?string $type): string
    {
        return route(self::createRoute($type));
    }
}
