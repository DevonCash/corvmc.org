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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credit_type', 50);
            $table->integer('amount'); // Positive = credit, Negative = debit; stored in smallest unit
            $table->integer('balance_after'); // Stored in smallest unit
            $table->string('source', 100); // 'monthly_allocation', 'admin_grant', 'reservation_usage', 'promo_code'
            $table->bigInteger('source_id')->nullable(); // ID of reservation, subscription, promo, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['source', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
