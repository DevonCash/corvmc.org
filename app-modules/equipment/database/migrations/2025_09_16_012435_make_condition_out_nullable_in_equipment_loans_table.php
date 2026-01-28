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
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropColumn('condition_out');
        });

        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->enum('condition_out', ['excellent', 'good', 'fair', 'poor', 'needs_repair'])->nullable()->after('borrower_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropColumn('condition_out');
        });

        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->enum('condition_out', ['excellent', 'good', 'fair', 'poor', 'needs_repair'])->after('borrower_id');
        });
    }
};
