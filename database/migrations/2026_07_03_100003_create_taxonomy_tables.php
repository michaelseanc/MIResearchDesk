<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurisdictions and tags are DATA, not hardcoded constants, so the platform ports to any
 * newsroom (not just Monument/El Paso County). Tags are polymorphic across entities,
 * documents, stories, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');                 // town | county | school_district | state | special_district
            $table->foreignId('parent_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('issue'); // issue | geography | campaign | project
            $table->timestamps();
            $table->unique(['organization_id', 'kind', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('jurisdictions');
    }
};
