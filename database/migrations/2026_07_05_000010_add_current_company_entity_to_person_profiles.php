<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional link from a person to the organization Entity that is their current employer. When set,
 * an "employed_by" relationship is synced so the employment appears on the relationship graph and
 * is clickable — while the plain current_company text field remains for quick, unlinked capture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->foreignId('current_company_entity_id')->nullable()->after('current_company')
                ->constrained('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_entity_id');
        });
    }
};
