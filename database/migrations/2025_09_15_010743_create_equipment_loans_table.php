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
        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->foreignId('borrower_id')->constrained('users')->onDelete('cascade');
            
            // Loan timing
            $table->timestamp('checked_out_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            
            // Status and condition
            $table->enum('status', ['active', 'overdue', 'returned', 'lost'])->default('active');
            $table->enum('condition_out', ['excellent', 'good', 'fair', 'poor', 'needs_repair']);
            $table->enum('condition_in', ['excellent', 'good', 'fair', 'poor', 'needs_repair'])->nullable();
            
            // Financial
            $table->decimal('security_deposit', 8, 2)->default(0);
            $table->decimal('rental_fee', 8, 2)->default(0);
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('damage_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['equipment_id', 'status']);
            $table->index(['borrower_id', 'status']);
            $table->index('due_at');
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loans');
    }
};