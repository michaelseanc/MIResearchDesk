<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->string('linkedin_url', 1024)->nullable()->after('professional_role');
        });
    }

    public function down(): void
    {
        Schema::table('person_profiles', fn (Blueprint $table) => $table->dropColumn('linkedin_url'));
    }
};
