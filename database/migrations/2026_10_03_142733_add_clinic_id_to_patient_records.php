<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('philhealth_id')->constrained('clinics')->onDelete('set null');
            $table->dropColumn('head_of_clinic');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->string('head_of_clinic')->after('philhealth_id');
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });
    }
};