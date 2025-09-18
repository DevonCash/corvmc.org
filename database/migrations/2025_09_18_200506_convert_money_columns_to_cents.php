<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert reservations.cost from decimal(8,2) to integer (cents)
        Schema::table('reservations', function (Blueprint $table) {
            // Add new column for cents
            $table->integer('cost_cents')->default(0)->after('cost');
        });

        // Convert existing cost data from dollars to cents
        DB::statement('UPDATE reservations SET cost_cents = ROUND(cost * 100)');

        // Remove old cost column and rename cost_cents to cost
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('cost_cents', 'cost');
        });

        // Convert transactions.amount from decimal(10,2) to integer (cents)
        // First drop any indexes that reference the amount column
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasIndex('transactions', 'idx_transactions_sustaining_members')) {
                $table->dropIndex('idx_transactions_sustaining_members');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Add new column for cents
            $table->integer('amount_cents')->default(0)->after('amount');
        });

        // Convert existing amount data from dollars to cents
        DB::statement('UPDATE transactions SET amount_cents = ROUND(amount * 100)');

        // Remove old amount column and rename amount_cents to amount
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
        });

        // Recreate the index with the new column type
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['email', 'type', 'amount', 'created_at'], 'idx_transactions_sustaining_members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert reservations.cost from integer (cents) back to decimal(8,2)
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('cost_dollars', 8, 2)->default(0)->after('cost');
        });

        // Convert existing cost data from cents to dollars
        DB::statement('UPDATE reservations SET cost_dollars = cost / 100.0');

        // Remove old cost column and rename cost_dollars to cost
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('cost_dollars', 'cost');
        });

        // Convert transactions.amount from integer (cents) back to decimal(10,2)
        // First drop the recreated index
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasIndex('transactions', 'idx_transactions_sustaining_members')) {
                $table->dropIndex('idx_transactions_sustaining_members');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount_dollars', 10, 2)->default(0)->after('amount');
        });

        // Convert existing amount data from cents to dollars
        DB::statement('UPDATE transactions SET amount_dollars = amount / 100.0');

        // Remove old amount column and rename amount_dollars to amount
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_dollars', 'amount');
        });

        // Recreate the original index
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['email', 'type', 'amount', 'created_at'], 'idx_transactions_sustaining_members');
        });
    }
};