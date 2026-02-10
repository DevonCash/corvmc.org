<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate charges that were paid via credits to the new CoveredByCredits status.
     */
    public function up(): void
    {
        DB::table('charges')
            ->where('status', 'paid')
            ->where('payment_method', 'credits')
            ->update(['status' => 'covered_by_credits']);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('charges')
            ->where('status', 'covered_by_credits')
            ->update(['status' => 'paid']);
    }
};
