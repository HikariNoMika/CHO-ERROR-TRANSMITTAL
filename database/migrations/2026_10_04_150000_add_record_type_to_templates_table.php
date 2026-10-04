<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Which record type this layout serves. NULL would mean "shared",
            // but every layout now belongs to exactly one type: PCU Success
            // no longer prints anything, so it has no template at all.
            $table->string('record_type', 20)->nullable()->after('name');
            $table->index(['record_type', 'is_active']);
        });

        // An existing install has one live template. It was the PCU Error
        // layout, so that is what it becomes.
        DB::table('templates')->whereNull('record_type')->update(['record_type' => 'error']);
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['record_type', 'is_active']);
            $table->dropColumn('record_type');
        });
    }
};
