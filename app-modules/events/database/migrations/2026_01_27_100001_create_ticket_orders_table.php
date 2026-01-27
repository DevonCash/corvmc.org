<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create ticket_orders table.
     *
     * A purchase transaction for event tickets. Implements Chargeable interface
     * for integration with the Finance module's payment system.
     */
    public function up(): void
    {
        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('email')->nullable()->index();
            $table->string('name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price')->comment('Price per ticket at purchase time in cents');
            $table->unsignedBigInteger('subtotal')->comment('quantity × unit_price in cents');
            $table->unsignedBigInteger('discount')->default(0)->comment('Member discount in cents');
            $table->unsignedBigInteger('fees')->default(0)->comment('Stripe fees if covered in cents');
            $table->unsignedBigInteger('total')->comment('Final amount charged in cents');
            $table->boolean('covers_fees')->default(false);
            $table->boolean('is_door_sale')->default(false);
            $table->string('payment_method')->nullable()->comment('stripe, cash, card, comp');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_orders');
    }
};
