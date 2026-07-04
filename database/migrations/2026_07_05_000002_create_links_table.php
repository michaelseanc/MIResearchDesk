<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web presence + references for an entity: official websites, social accounts, and pasted article
 * links. Manual article links live here as lightweight references; the future WordPress/Newspack
 * sync populates the separate `articles` table for the newsroom's own coverage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('kind')->default('website'); // website | social | article | other
            $table->string('platform')->nullable();      // for social: x | facebook | linkedin | instagram | youtube ...
            $table->string('url', 1024);
            $table->string('title')->nullable();          // label, handle, or article headline
            $table->date('published_at')->nullable();     // article publication date, when known
            $table->text('note')->nullable();
            $table->string('sensitivity')->default('internal');
            $table->timestamps();
            $table->index(['organization_id', 'entity_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
