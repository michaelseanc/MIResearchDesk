<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type badge color for connections, so newsroom staff can color-code the connection vocabulary
 * (e.g. Registered Agent = green) without a developer. Falls back to the category mapping when null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relationship_types', function (Blueprint $table) {
            $table->string('color')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('relationship_types', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
