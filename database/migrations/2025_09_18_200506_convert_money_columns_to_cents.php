<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
    }
};
