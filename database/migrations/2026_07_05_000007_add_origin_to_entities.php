<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes auto-created finance actors (donors/committees materialized from TRACER imports)
 * from hand-curated dossier subjects. The People & Organizations list hides origin=finance_import
 * by default; the graph and finance modules still use every entity. Backfills existing finance-
 * derived entities so the split takes effect immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('origin')->nullable()->after('entity_type')->index();
        });

        // Backfill: any entity referenced by a finance transaction (as contributor or committee)
        // that hasn't been curated is marked as an imported finance actor.
        $ids = DB::table('finance_transactions')->whereNotNull('contributor_entity_id')->pluck('contributor_entity_id')
            ->merge(DB::table('finance_transactions')->whereNotNull('committee_entity_id')->pluck('committee_entity_id'))
            ->filter()->unique()->values()->all();

        if (! empty($ids)) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('entities')->whereIn('id', $chunk)->whereNull('origin')
                    ->update(['origin' => 'finance_import']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('entities', fn (Blueprint $table) => $table->dropColumn('origin'));
    }
};
