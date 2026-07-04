<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entity-first model: people, orgs, committees, government bodies, projects, properties are
 * all rows in `entities`. Type-specific fields live in 1:1 extension tables. The MVP UI
 * surfaces person + organization; the base table already supports all types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // permanent stable identifier
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');                  // person | organization | committee | government_body | business | project | property | public_office | media_outlet
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('status')->default('active');    // active | former | dissolved | historical | deceased | unknown
            $table->string('primary_geography')->nullable();
            $table->foreignId('primary_jurisdiction_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->text('public_summary')->nullable();     // publishable factual background
            $table->text('internal_summary')->nullable();   // private reporting context
            $table->text('why_it_matters')->nullable();     // newsroom "why this matters" note
            $table->string('sensitivity')->default('internal'); // public | internal | confidential | sealed
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('last_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'entity_type']);
            $table->index(['organization_id', 'sensitivity']);
        });

        Schema::create('entity_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_type')->nullable();  // maiden | dba | abbreviation | spelling_variant | committee_name | campaign_name
            $table->timestamps();
            $table->index(['organization_id', 'alias']);
        });

        // 1:1 extension for people
        Schema::create('person_profiles', function (Blueprint $table) {
            $table->foreignId('entity_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->string('known_names')->nullable();
            $table->string('professional_role')->nullable();
            $table->string('geography_detail')->nullable();
            $table->string('source_status')->nullable();          // official | source | subject | critic | advocate | expert | resident ...
            $table->string('confidentiality_status')->nullable(); // on_record | background | not_for_attribution | off_the_record | confidential
            $table->text('dossier_summary')->nullable();
            $table->text('reliability_notes')->nullable();        // INTERNAL ONLY — never shown publicly
            $table->timestamps();
        });

        // 1:1 extension for organizations
        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->foreignId('entity_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('dba_name')->nullable();
            $table->string('org_subtype')->nullable();  // business | pac | committee | nonprofit | law_firm | consulting | hoa | school_district | agency ...
            $table->string('website')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('registered_agent')->nullable();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contact_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('method');                 // phone | email | signal | social | in_person
            $table->string('value');
            $table->boolean('is_preferred')->default(false);
            $table->string('restrictions')->nullable(); // do_not_call | text_only | no_voicemail | source_safe ...
            $table->string('sensitivity')->default('internal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_methods');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('person_profiles');
        Schema::dropIfExists('entity_aliases');
        Schema::dropIfExists('entities');
    }
};
