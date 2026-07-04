<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named relationship-graph views (focus entity + depth + filters), e.g. "Buc-ee's network" or
 * "2027 county commission". Org-scoped so the whole newsroom shares them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_graph_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('params')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_graph_views');
    }
};
