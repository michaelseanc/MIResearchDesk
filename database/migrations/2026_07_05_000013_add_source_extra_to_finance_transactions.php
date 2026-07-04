<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON catch-all for dataset-specific source columns that don't have a first-class home on the
 * shared transactions table — e.g. a loan's outstanding balance and interest rate. Keeps the core
 * columns generic (good for the eventual public-product split) while preserving the raw source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->json('source_extra')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropColumn('source_extra');
        });
    }
};
