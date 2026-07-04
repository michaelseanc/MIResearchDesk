<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRACER's "Jurisdiction" column — the county a race/committee belongs to (e.g. "EL PASO"), or
 * "STATEWIDE"/"FEDERAL" for non-local races. Persisted so imports can filter by county and the
 * Explorer can slice by it later. Present in all three datasets (contributions/expenditures/loans).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('jurisdiction')->nullable()->after('state');
            $table->index(['organization_id', 'jurisdiction']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'jurisdiction']);
            $table->dropColumn('jurisdiction');
        });
    }
};
