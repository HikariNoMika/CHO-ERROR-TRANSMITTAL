<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->onDelete('cascade');
            $table->string('placeholder'); // e.g., 'patient_name'
            $table->string('label'); // Human readable label
            $table->enum('type', ['text', 'date', 'image'])->default('text');
            $table->boolean('is_required')->default(false);
            $table->string('validation_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['template_id', 'placeholder']);
            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fields');
    }
};