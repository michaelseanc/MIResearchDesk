<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * contact_interactions and public_records_requests use the TracksAuthor trait, which stamps
 * updated_by. They were created with only created_by; add the missing column. No DB-level FK
 * (SQLite cannot add one via ALTER); integrity is enforced at the app layer, consistent with
 * how other author columns behave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_interactions', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        });

        Schema::table('public_records_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('contact_interactions', fn (Blueprint $table) => $table->dropColumn('updated_by'));
        Schema::table('public_records_requests', fn (Blueprint $table) => $table->dropColumn('updated_by'));
    }
};
