<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Contributions/Expenditures/Loans lists all filter by (organization_id, data_type) and sort by
 * transaction_date. Without a composite index covering both, MySQL filesorts every matching row on
 * each page load (tens of thousands, growing). This index lets it filter AND return sorted rows from
 * the index directly — the key scalability fix for those tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->index(['organization_id', 'data_type', 'transaction_date'], 'ft_org_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex('ft_org_type_date_idx');
        });
    }
};
