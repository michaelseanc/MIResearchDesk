<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured, multi-address support for entities. Kept as discrete components (not a text blob)
 * because city/state/ZIP are matchable — they feed campaign-finance donor matching and property
 * work later. A person or org can hold several typed addresses (home, mailing, registered, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('label')->default('primary'); // home | work | mailing | registered | property | other
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('US');
            $table->boolean('is_primary')->default(false);
            $table->string('sensitivity')->default('internal');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'entity_id']);
            $table->index(['organization_id', 'postal_code']); // for donor/address matching later
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
