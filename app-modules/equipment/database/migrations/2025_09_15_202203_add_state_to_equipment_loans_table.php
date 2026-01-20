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
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->string('state')->default('pending')->after('status');
        });

        // Copy existing status values to state for initial migration
        DB::statement("UPDATE equipment_loans SET state = CASE 
            WHEN status = 'active' THEN 'checked_out'
            WHEN status = 'returned' THEN 'returned' 
            WHEN status = 'overdue' THEN 'overdue'
            ELSE 'pending'
        END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
