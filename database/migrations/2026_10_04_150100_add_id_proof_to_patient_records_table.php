<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // Photo of the ID document itself, as distinct from the photo of the
            // patient holding the ID. Medical Mission records need both.
            $table->string('id_proof_image_path')->nullable()->after('empanelment_error_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn('id_proof_image_path');
        });
    }
};
