<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite: doctrine/dbal needed for column change; use table rebuild fallback.
        try {
            Schema::table('patient_records', function (Blueprint $table) {
                $table->date('birthdate')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Fresh-schema fallback is unnecessary here; rethrow with context.
            throw $e;
        }
    }

    public function down(): void
    {
        DB::table('patient_records')->whereNull('birthdate')->update(['birthdate' => '2000-01-01']);
        Schema::table('patient_records', function (Blueprint $table) {
            $table->date('birthdate')->nullable(false)->change();
        });
    }
};