<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign-finance ingestion from Colorado TRACER bulk data. An import batch records one pull of
 * an official CSV (preserving the raw file + its source hash); transactions hold the raw source
 * fields alongside normalized links to entities and a match state, so raw data and the newsroom's
 * interpretation stay separable. Built source-agnostic (source column) for other jurisdictions later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('tracer');       // tracer | municipal | manual
            $table->string('data_type')->default('contributions'); // contributions | expenditures | loans
            $table->unsignedSmallInteger('year');
            $table->string('source_url', 1024)->nullable();
            $table->string('raw_file_path')->nullable();        // preserved original ZIP/CSV (private disk)
            $table->string('file_hash', 64)->nullable();        // sha256 of downloaded file
            $table->timestamp('source_last_modified')->nullable();
            $table->string('status')->default('pending');       // pending | downloading | parsing | completed | failed
            $table->json('filter')->nullable();                 // {terms:[], cities:[], zips:[]}
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'data_type', 'year']);
        });

        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('finance_import_batches')->nullOnDelete();
            $table->string('source')->default('tracer');
            $table->string('data_type')->default('contributions');
            $table->unsignedSmallInteger('year')->nullable();

            // --- Raw source fields (preserved verbatim from TRACER) ---
            $table->string('file_number')->nullable();          // source transaction/file number
            $table->string('committee_type')->nullable();
            $table->string('committee_name')->nullable();
            $table->string('candidate_name')->nullable();
            $table->string('contributor_type')->nullable();
            $table->string('contributor_name')->nullable();     // "Name" (payee for expenditures)
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->string('txn_subtype')->nullable();          // the source "Type" column
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('received_by')->nullable();
            $table->string('amended')->nullable();              // source "Amended" flag

            // --- Normalized newsroom interpretation (kept separate from raw) ---
            $table->foreignId('committee_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('candidate_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('contributor_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('match_state')->default('unmatched'); // unmatched | auto | approved

            $table->string('row_hash', 64)->nullable();          // dedupe key across re-imports
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'committee_name']);
            $table->index(['organization_id', 'contributor_name']);
            $table->index(['organization_id', 'transaction_date']);
            $table->index(['organization_id', 'match_state']);
            $table->unique(['organization_id', 'row_hash']);     // idempotent re-imports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_import_batches');
    }
};
