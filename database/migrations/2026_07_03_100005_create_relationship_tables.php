<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relationships are the heart of the system: structured, dated, reviewable newsroom assertions,
 * not mere graph lines. A relationship cannot be marked `verified` without at least one row in
 * relationship_evidence (enforced in the model). Evidence join table lives in the documents
 * migration, after document_citations exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // e.g. employed_by, donated_to
            $table->string('label')->nullable();        // human display, e.g. "Employed by"
            $table->boolean('is_directional')->default(true);
            $table->string('inverse_name')->nullable(); // e.g. "employer of"
            $table->string('category')->nullable();     // employment | donation | board | consultant | legal | family | project | opposition ...
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('to_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('relationship_type_id')->constrained('relationship_types')->restrictOnDelete();
            $table->boolean('is_directional')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');            // active | former | historical | disputed | unknown
            $table->string('verification_state')->default('lead');  // verified | corroborated | reported | lead | disputed | disproven
            $table->unsignedTinyInteger('confidence')->nullable();  // 1–5
            $table->foreignId('issue_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('sensitivity')->default('internal');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('last_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'from_entity_id']);
            $table->index(['organization_id', 'to_entity_id']);
            $table->index(['organization_id', 'verification_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
        Schema::dropIfExists('relationship_types');
    }
};
