<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRACER's bulk contribution file carries a separate Employer field (distinct from Occupation),
 * which matters for donor analysis. Add it to the transactions table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('employer')->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', fn (Blueprint $table) => $table->dropColumn('employer'));
    }
};
