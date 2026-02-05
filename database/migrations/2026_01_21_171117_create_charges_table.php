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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('chargeable'); // chargeable_type, chargeable_id
            $table->unsignedBigInteger('amount'); // gross amount in cents
            $table->json('credits_applied')->nullable(); // {"FreeHours": 4}
            $table->unsignedBigInteger('net_amount'); // amount after credits, in cents
            $table->string('status')->default('pending'); // ChargeStatus enum
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // morphs() already creates an index on (chargeable_type, chargeable_id)
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
