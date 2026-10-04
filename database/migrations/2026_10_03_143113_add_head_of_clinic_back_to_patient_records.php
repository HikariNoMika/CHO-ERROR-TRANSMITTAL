<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // No foreign key to drop: clinic_id was added without one, since the
            // clinics table it referenced never existed.
            $table->dropColumn('clinic_id');
            $table->string('head_of_clinic')->after('philhealth_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn('head_of_clinic');
            $table->unsignedBigInteger('clinic_id')->nullable()->after('philhealth_id');
        });
    }
};
