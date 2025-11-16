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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('user_id')->constrained();
            $table->string('status')->default('pending'); // e.g., 'pending', 'confirmed', 'cancelled'
            $table->decimal('cost', 8, 2)->default(0);
            $table->string('payment_status')->default('unpaid'); // 'unpaid', 'paid', 'comped', 'refunded'
            $table->string('payment_method')->nullable(); // 'cash', 'card', 'venmo', 'comp', etc.
            $table->timestamp('paid_at')->nullable();
            $table->text('payment_notes')->nullable(); // admin notes about payment
            $table->decimal('hours_used', 4, 2)->default(0);
            $table->decimal('free_hours_used', 4, 2)->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('reserved_until')->nullable();
        });

        // Pivot table for additional users to notify
        Schema::create('reservation_users', function (Blueprint $table) {
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_users');
        Schema::dropIfExists('reservations');
    }
};
