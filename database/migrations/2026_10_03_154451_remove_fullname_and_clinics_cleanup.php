<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // patient_name is the single canonical name field; fullname was redundant.
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn('fullname');
        });

        // The clinics table was superseded by the settings-based
        // facility/head-of-clinic configuration and is unused.
        Schema::dropIfExists('clinics');
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->string('fullname')->nullable()->after('patient_name');
        });

        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};