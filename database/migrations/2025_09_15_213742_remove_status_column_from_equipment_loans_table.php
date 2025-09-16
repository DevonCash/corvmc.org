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
            // Drop indexes that reference the status column
            $table->dropIndex(['equipment_id', 'status']);
            $table->dropIndex(['borrower_id', 'status']);
            $table->dropIndex(['status', 'due_at']);
            
            // Now drop the status column
            $table->dropColumn('status');
            
            // Add new indexes based on state (though state queries may not need traditional indexes)
            $table->index(['equipment_id', 'state']);
            $table->index(['borrower_id', 'state']);
            $table->index(['state', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            // Drop state-based indexes
            $table->dropIndex(['equipment_id', 'state']);
            $table->dropIndex(['borrower_id', 'state']);
            $table->dropIndex(['state', 'due_at']);
            
            // Re-add the status column
            $table->enum('status', ['active', 'overdue', 'returned', 'lost'])->default('active');
            
            // Re-create original indexes
            $table->index(['equipment_id', 'status']);
            $table->index(['borrower_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }
};
