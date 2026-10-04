<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // ATC-slip style fields. Optional: only templates that use
            // these placeholders need them filled in.
            $table->date('appointment_date')->nullable()->after('philhealth_id');
            $table->string('auth_transaction_code', 100)->nullable()->after('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn(['appointment_date', 'auth_transaction_code']);
        });
    }
};