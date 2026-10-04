<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PCU Success is a data-only log now: it records inputs and exports,
        // but prints nothing. Without a template there is nothing for it to
        // point at.
        Schema::table('patient_records', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
        });
    }
};
