<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // No foreign key: the clinics table this pointed at was never created
            // and is dropped again further down the chain. SQLite tolerated the
            // dangling reference; MySQL/MariaDB reject it outright, so the column
            // is added plain and the key left off.
            $table->unsignedBigInteger('clinic_id')->nullable()->after('philhealth_id');
            $table->dropColumn('head_of_clinic');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->string('head_of_clinic')->after('philhealth_id');
            $table->dropColumn('clinic_id');
        });
    }
};
