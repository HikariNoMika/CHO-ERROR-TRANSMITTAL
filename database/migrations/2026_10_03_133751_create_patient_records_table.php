<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_records', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('fullname')->nullable();
            $table->date('birthdate');
            $table->string('philhealth_id');
            $table->string('head_of_clinic');
            $table->date('date_today')->nullable(); // Auto-generated at generation time
            $table->string('image_with_id_path')->nullable();
            $table->string('empanelment_error_image_path')->nullable();
            $table->foreignId('template_id')->constrained('templates')->onDelete('restrict');
            $table->string('generated_file_path')->nullable();
            $table->enum('status', ['draft', 'generated', 'printed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('patient_name');
            $table->index('philhealth_id');
            $table->index('birthdate');
            $table->index('created_at');
            $table->index('status');
            $table->index('template_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_records');
    }
};