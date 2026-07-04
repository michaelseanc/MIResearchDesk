<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how many committees / donor connections the post-import donor-network build produced,
 * so the batch row can report "graph updated: N committees, N connections" alongside row counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_import_batches', function (Blueprint $table) {
            $table->unsignedInteger('network_committees')->nullable()->after('rows_skipped');
            $table->unsignedInteger('network_connections')->nullable()->after('network_committees');
        });
    }

    public function down(): void
    {
        Schema::table('finance_import_batches', function (Blueprint $table) {
            $table->dropColumn(['network_committees', 'network_connections']);
        });
    }
};
