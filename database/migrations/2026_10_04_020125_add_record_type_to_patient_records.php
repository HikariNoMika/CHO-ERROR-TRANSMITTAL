<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // error | success — existing records default to error.
            $table->string('record_type', 20)->default('error')->after('status');
            $table->index('record_type');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropIndex(['record_type']);
            $table->dropColumn('record_type');
        });
    }
};