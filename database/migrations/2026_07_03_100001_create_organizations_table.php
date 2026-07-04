<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant. Every other table hangs off an organization_id. Monument is row 1.
 * Designed multi-tenant from day one so a future SaaS sale needs no schema rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | suspended | archived
            $table->json('settings')->nullable();         // per-tenant config (jurisdictions defaults, branding, etc.)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
