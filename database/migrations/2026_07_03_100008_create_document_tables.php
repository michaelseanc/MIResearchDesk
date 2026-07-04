<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents are first-class research objects stored on a PRIVATE disk (never a public URL).
 * document_citations are the atomic unit of evidence — page/paragraph/quote — that relationships,
 * positions, and claims cite. The three *_evidence join tables live here because they reference
 * document_citations, which must exist first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type')->nullable(); // public_record | campaign_filing | meeting_packet | interview | email | court_filing | web_capture | foia | source_doc
            $table->string('origin')->nullable();
            $table->string('original_url')->nullable();
            $table->string('file_path')->nullable();    // private disk path
            $table->string('file_hash', 64)->nullable(); // sha256 — dedupe / integrity
            $table->string('mime')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->date('capture_date')->nullable();
            $table->date('document_date')->nullable();
            $table->string('sensitivity')->default('internal');
            $table->longText('ocr_text')->nullable();
            $table->string('retention_status')->default('active'); // active | archived | superseded | destroyed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'sensitivity']);
            $table->index(['organization_id', 'file_hash']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('version_relationship'); // original | amended | replacement | corrected
            $table->string('file_path');
            $table->string('file_hash', 64)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->string('paragraph')->nullable();
            $table->text('quote')->nullable();
            $table->string('image_ref')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'document_id']);
        });

        Schema::create('document_entity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'entity_id']);
        });

        Schema::create('document_story_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'story_id']);
        });

        // Evidence join tables — each points a claim/relationship/position at a specific citation.
        Schema::create('relationship_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('relationship_id')->constrained('relationships')->cascadeOnDelete();
            $table->foreignId('document_citation_id')->constrained('document_citations')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['relationship_id', 'document_citation_id'], 'rel_evidence_unique');
        });

        Schema::create('position_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions_interests')->cascadeOnDelete();
            $table->foreignId('document_citation_id')->constrained('document_citations')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['position_id', 'document_citation_id'], 'pos_evidence_unique');
        });

        Schema::create('claim_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignId('document_citation_id')->constrained('document_citations')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['claim_id', 'document_citation_id'], 'claim_evidence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_evidence');
        Schema::dropIfExists('position_evidence');
        Schema::dropIfExists('relationship_evidence');
        Schema::dropIfExists('document_story_links');
        Schema::dropIfExists('document_entity_links');
        Schema::dropIfExists('document_citations');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
