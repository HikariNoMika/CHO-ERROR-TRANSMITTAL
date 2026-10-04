<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
            $table->string('head_of_clinic')->after('philhealth_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn('head_of_clinic');
            $table->foreignId('clinic_id')->nullable()->after('philhealth_id')->constrained('clinics')->onDelete('set null');
        });
    }
};