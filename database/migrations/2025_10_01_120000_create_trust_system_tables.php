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
        // User trust balances (replaces JSON field)
        Schema::create('user_trust_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 100)->default('global'); // FQCN or 'global'
            $table->integer('balance')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'content_type']);
            $table->index('user_id');
            $table->index(['content_type', 'balance']); // For querying by trust level
        });

        // Trust transactions (complete audit trail)
        Schema::create('trust_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 100);
            $table->integer('points'); // Positive = award, Negative = penalty
            $table->integer('balance_after');
            $table->string('reason', 255);
            $table->string('source_type', 50); // 'successful_content', 'minor_violation', etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('awarded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('content_type');
            $table->index(['source_type', 'source_id']);
        });

        // Trust level achievements (optional: track when users reach milestones)
        Schema::create('trust_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 100);
            $table->string('level', 20); // 'trusted', 'verified', 'auto_approved'
            $table->timestamp('achieved_at');

            $table->unique(['user_id', 'content_type', 'level']);
            $table->index('user_id');
            $table->index('achieved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trust_achievements');
        Schema::dropIfExists('trust_transactions');
        Schema::dropIfExists('user_trust_balances');
    }
};
