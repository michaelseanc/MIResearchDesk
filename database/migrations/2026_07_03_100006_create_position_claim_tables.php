<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positions/interests and claims are what keep FACT separate from INTERPRETATION. The record_type
 * and verification_state carry the epistemic status structurally — there is deliberately no
 * freeform "motivation" field. Evidence join tables live in the documents migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('topic_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->string('record_type');   // public_position | financial_interest | stated_motivation | reported_motivation | editorial_analysis | vote | endorsement | opposition
            $table->text('summary');
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('verification_status')->default('reported'); // verified | attributed | reported | disputed | unresolved
            $table->string('visibility')->default('internal');         // public | internal | confidential
            $table->string('review_flag')->nullable();                 // needs_right_to_respond | legal_review | source_corroboration | null
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'entity_id']);
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->text('statement');
            $table->string('verification_state')->default('lead'); // verified | corroborated | reported | lead | disputed | disproven
            $table->string('sensitivity')->default('internal');
            $table->string('review_flag')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'verification_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
        Schema::dropIfExists('positions_interests');
    }
};
