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
        Schema::create('credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credit_type', 50);
            $table->integer('amount'); // Stored in smallest unit
            $table->string('frequency', 20); // 'monthly', 'weekly', 'one_time'
            $table->string('source', 100); // 'stripe_subscription', 'manual', 'promotion'
            $table->string('source_id')->nullable(); // Stripe subscription ID, etc.
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_allocated_at')->nullable();
            $table->timestamp('next_allocation_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('next_allocation_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_allocations');
    }
};
