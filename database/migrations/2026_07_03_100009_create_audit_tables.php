<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_events is APPEND-ONLY and hash-chained (payload_hash + prev_hash) so tampering is
 * detectable. It records every access/edit/download/export of sensitive material — and is the
 * ledger the future source vault relies on. Application code must never UPDATE or DELETE rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                    // viewed | created | updated | deleted | downloaded | exported | unsealed ...
            $table->nullableMorphs('auditable');         // auditable_type + auditable_id
            $table->string('sensitivity_touched')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();         // small structured detail (no sensitive plaintext)
            $table->string('payload_hash', 64)->nullable();
            $table->string('prev_hash', 64)->nullable(); // chain link to the previous event
            $table->timestamp('created_at')->nullable(); // insert-only; no updated_at
            $table->index(['organization_id', 'auditable_type', 'auditable_id']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('params');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('audit_events');
    }
};
