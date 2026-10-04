<?php

namespace App\Services;

use App\Models\PatientRecord;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Per-type template mode.
 *
 * Each printing record type keeps one live template of its own, so uploading a
 * replacement only affects that type: records of that type are re-pointed at the
 * new file and their documents are rebuilt. Other types keep the layout they
 * already had.
 *
 * Documents already produced are never rewritten. document_generations keeps one
 * append-only row per generation, including the template version used, so the
 * history of a record still shows which layout each document came from.
 */
class TemplateAdoptionService
{
    public function __construct(protected DocumentGenerationService $documents) {}

    /**
     * Point every record of the template's own type at $template and rebuild the
     * documents that already exist.
     *
     * Only records that already have a document are rebuilt. Draft records have
     * nothing to replace, and generating them here would produce files for
     * records the user never completed.
     *
     * @return array{migrated:int,existing:int,rebuilt:int,failed:array<int,string>}
     */
    public function adopt(Template $template, User $user): array
    {
        // Scoped to the type: a PCU Error upload must not drag Medical Mission
        // records, or data-only records that have no template, onto it.
        $ofType = PatientRecord::where('record_type', $template->record_type);

        $migrated = (clone $ofType)
            ->where('template_id', '!=', $template->id)
            ->update(['template_id' => $template->id]);

        $records = (clone $ofType)->whereNotNull('generated_file_path')->get();

        $rebuilt = 0;
        $failed = [];

        foreach ($records as $record) {
            // The bulk update above does not refresh an already-loaded relation,
            // and generate() reads the template through it.
            $record->unsetRelation('template');
            $record->load('template');

            // generate() sets status to 'generated'. Keep a printed record
            // printed: the one-way lifecycle must not be undone by a template
            // swap.
            $wasPrinted = $record->status === 'printed';

            try {
                $this->documents->generate($record, $user);

                if ($wasPrinted) {
                    $record->update(['status' => 'printed']);
                }

                $rebuilt++;
            } catch (\Throwable $e) {
                // One bad record must not abandon the rest.
                Log::error('Template adoption could not rebuild a record', [
                    'record_id' => $record->id,
                    'template_id' => $template->id,
                    'exception' => $e->getMessage(),
                ]);

                if ($wasPrinted) {
                    $record->update(['status' => 'printed']);
                }

                $failed[] = $record->patient_name;
            }
        }

        return [
            'migrated' => $migrated,
            'existing' => $records->count(),
            'rebuilt' => $rebuilt,
            'failed' => $failed,
        ];
    }

    /**
     * Human-readable summary for the flash message.
     */
    public function summarise(array $result): string
    {
        $parts = [];

        if ($result['migrated'] > 0) {
            $parts[] = $result['migrated'].' record'.($result['migrated'] === 1 ? '' : 's')
                .' now use this template';
        }

        if ($result['rebuilt'] > 0) {
            $parts[] = $result['rebuilt'].' document'.($result['rebuilt'] === 1 ? '' : 's')
                .' rebuilt';
        }

        if ($result['failed'] !== []) {
            $parts[] = count($result['failed']).' could not be rebuilt: '
                .implode(', ', array_slice($result['failed'], 0, 3))
                .(count($result['failed']) > 3 ? '…' : '');
        }

        return $parts === [] ? 'No existing records needed updating.' : implode('. ', $parts).'.';
    }
}
