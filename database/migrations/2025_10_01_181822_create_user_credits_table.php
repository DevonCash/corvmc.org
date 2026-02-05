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
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credit_type', 50)->default('free_hours'); // free_hours, equipment_credits, bonus_hours, promo_hours
            $table->integer('balance')->default(0); // Stored in smallest unit: blocks for free_hours, credits for equipment
            $table->integer('max_balance')->nullable(); // Cap for equipment_credits (250), NULL for unlimited
            $table->boolean('rollover_enabled')->default(false); // true for equipment_credits, false for free_hours
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'credit_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }
};
