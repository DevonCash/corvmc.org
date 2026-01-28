<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Change cost columns to store cents as integers
            $table->unsignedInteger('estimated_cost_cents')->nullable()->after('priority');
            $table->unsignedInteger('actual_cost_cents')->nullable()->after('estimated_cost_cents');
        });

        // Migrate existing data (convert dollars to cents)
        DB::statement('UPDATE equipment_damage_reports SET estimated_cost_cents = ROUND(estimated_cost * 100) WHERE estimated_cost IS NOT NULL');
        DB::statement('UPDATE equipment_damage_reports SET actual_cost_cents = ROUND(actual_cost * 100) WHERE actual_cost IS NOT NULL');

        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Drop old decimal columns
            $table->dropColumn(['estimated_cost', 'actual_cost']);
        });

        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Rename new columns
            $table->renameColumn('estimated_cost_cents', 'estimated_cost');
            $table->renameColumn('actual_cost_cents', 'actual_cost');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Add back decimal columns
            $table->decimal('estimated_cost_dollars', 10, 2)->nullable()->after('priority');
            $table->decimal('actual_cost_dollars', 10, 2)->nullable()->after('estimated_cost_dollars');
        });

        // Migrate data back (convert cents to dollars)
        DB::statement('UPDATE equipment_damage_reports SET estimated_cost_dollars = estimated_cost / 100 WHERE estimated_cost IS NOT NULL');
        DB::statement('UPDATE equipment_damage_reports SET actual_cost_dollars = actual_cost / 100 WHERE actual_cost IS NOT NULL');

        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Drop integer columns
            $table->dropColumn(['estimated_cost', 'actual_cost']);
        });

        Schema::table('equipment_damage_reports', function (Blueprint $table) {
            // Rename back to original names
            $table->renameColumn('estimated_cost_dollars', 'estimated_cost');
            $table->renameColumn('actual_cost_dollars', 'actual_cost');
        });
    }
};
