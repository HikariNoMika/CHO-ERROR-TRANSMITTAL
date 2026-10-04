<?php

namespace App\Models;

use App\Support\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'record_type',
        'description',
        'file_path',
        'version',
        'is_active',
        'detected_placeholders',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'detected_placeholders' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The live template for one record type.
     *
     * Each printing type keeps its own layout, so "the active template" is only
     * meaningful together with the type it serves. Data-only types never have
     * one and always return null.
     */
    public static function activeFor(?string $recordType): ?self
    {
        $recordType = RecordType::normalise($recordType);

        if (! RecordType::usesTemplate($recordType)) {
            return null;
        }

        return static::where('is_active', true)
            ->where('record_type', $recordType)
            ->orderBy('id')
            ->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('sort_order');
    }

    public function patientRecords(): HasMany
    {
        return $this->hasMany(PatientRecord::class);
    }

    public function documentGenerations(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class);
    }
}
