<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile photo / logo for an entity, shown at the top of the dossier. Stored on the public disk
 * (photos are low-sensitivity and need a displayable URL); document evidence stays on the private
 * disk. If photos ever need to be private, swap in an authenticated media route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('entities', fn (Blueprint $table) => $table->dropColumn('photo_path'));
    }
};
