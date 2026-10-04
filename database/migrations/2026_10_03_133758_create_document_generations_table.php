<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_record_id')->constrained('patient_records')->onDelete('cascade');
            $table->foreignId('template_id')->constrained('templates')->onDelete('restrict');
            $table->string('template_version');
            $table->string('file_path');
            $table->foreignId('generated_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['patient_record_id', 'generated_at']);
            $table->index('generated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_generations');
    }
};