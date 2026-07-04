<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stories and issues (a story may be one article; an issue may run for years), plus their links
 * to entities, claims, contacts, tasks, and public-records requests. Contact interactions log
 * every touch with a source or subject under explicit attribution terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('story');   // story | investigation | ongoing_issue | beat | project
            $table->string('status')->default('lead');  // lead | reporting | records_pending | draft | edit | legal_review | published | follow_up | archived
            $table->string('priority')->default('normal'); // low | normal | high | urgent
            $table->text('central_question')->nullable();
            $table->text('why_it_matters')->nullable();
            $table->text('open_questions')->nullable();
            $table->text('known_facts')->nullable();
            $table->text('counterarguments')->nullable();
            $table->text('next_action')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('story_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('role_note')->nullable();
            $table->timestamps();
            $table->unique(['story_id', 'entity_id']);
        });

        Schema::create('story_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['story_id', 'claim_id']);
        });

        Schema::create('story_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('open'); // open | in_progress | done
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('contact_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->string('interaction_type'); // call | email | meeting | tip | interview | public_comment | records_request
            $table->timestamp('occurred_at')->nullable();
            $table->text('summary')->nullable();
            $table->string('attribution_terms')->nullable(); // on_record | background | deep_background | off_the_record | confidential
            $table->timestamp('follow_up_at')->nullable();
            $table->string('visibility')->default('internal'); // internal | sealed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'follow_up_at']);
        });

        // A contact interaction may involve additional named contacts on a story with terms
        Schema::create('story_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('attribution_terms')->nullable();
            $table->timestamps();
            $table->unique(['story_id', 'entity_id']);
        });

        Schema::create('public_records_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->string('agency');
            $table->text('subject');
            $table->date('submitted_at')->nullable();
            $table->date('due_at')->nullable();
            $table->string('status')->default('draft'); // draft | submitted | acknowledged | partial | fulfilled | denied | appealed
            $table->text('response_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_records_requests');
        Schema::dropIfExists('story_contacts');
        Schema::dropIfExists('contact_interactions');
        Schema::dropIfExists('story_tasks');
        Schema::dropIfExists('story_claims');
        Schema::dropIfExists('story_entities');
        Schema::dropIfExists('stories');
    }
};
