<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the entity-link columns on finance_transactions. These are queried per-entity constantly —
 * the donations-made/received dossier tabs, the network builder, and Tier-1 enrichment all filter
 * by contributor_entity_id / committee_entity_id. SQLite doesn't auto-index FK columns, so at CO
 * scale these were full scans over ~82k rows. Critical for enrichment/graph performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['organization_id', 'contributor_entity_id']);
            $table->index(['organization_id', 'committee_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'contributor_entity_id']);
            $table->dropIndex(['organization_id', 'committee_entity_id']);
        });
    }
};
