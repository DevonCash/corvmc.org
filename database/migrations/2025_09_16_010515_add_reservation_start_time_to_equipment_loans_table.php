<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            // Add reservation start time - when the equipment is reserved to start being used
            $table->timestamp('reserved_from')->nullable()->after('borrower_id');
            
            // Add index for reservation period queries
            $table->index(['equipment_id', 'reserved_from']);
            $table->index(['reserved_from', 'due_at']);
        });
        
        // Populate existing records with reserved_from = checked_out_at (or current time if null)
        DB::statement("UPDATE equipment_loans SET reserved_from = COALESCE(checked_out_at, created_at) WHERE reserved_from IS NULL");
        
        // Now make the column not nullable
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->timestamp('reserved_from')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropIndex(['equipment_id', 'reserved_from']);
            $table->dropIndex(['reserved_from', 'due_at']);
            $table->dropColumn('reserved_from');
        });
    }
};
