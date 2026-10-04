<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PatientRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_name',
        'birthdate',
        'philhealth_id',
        'appointment_date',
        'auth_transaction_code',
        'pcu_error_code',
        'head_of_clinic',
        'date_today',
        'image_with_id_path',
        'empanelment_error_image_path',
        'id_proof_image_path',
        'template_id',
        'generated_file_path',
        'status',
        'record_type',
        'created_by',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'appointment_date' => 'date',
        'date_today' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function documentGenerations(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class);
    }

    public function latestGeneration(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class)->latest('generated_at');
    }

    public function getImageWithIdUrlAttribute(): ?string
    {
        if (! $this->image_with_id_path) {
            return null;
        }

        return route('records.image', [$this, 'id']);
    }

    public function getEmpanelmentErrorImageUrlAttribute(): ?string
    {
        if (! $this->empanelment_error_image_path) {
            return null;
        }

        return route('records.image', [$this, 'error']);
    }

    public function getIdProofImageUrlAttribute(): ?string
    {
        if (! $this->id_proof_image_path) {
            return null;
        }

        return route('records.image', [$this, 'id_proof']);
    }

    public function getGeneratedFileUrlAttribute(): ?string
    {
        if (! $this->generated_file_path) {
            return null;
        }

        return Storage::disk('private')->url($this->generated_file_path);
    }

    public function getHeadOfClinicAttribute(): string
    {
        return $this->attributes['head_of_clinic'] ?? Setting::getHeadOfClinic();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isGenerated(): bool
    {
        return $this->status === 'generated';
    }

    public function isPrinted(): bool
    {
        return $this->status === 'printed';
    }
}
