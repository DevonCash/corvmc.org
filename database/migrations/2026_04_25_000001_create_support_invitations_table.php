<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('invitable_type');
            $table->unsignedBigInteger('invitable_id');
            $table->string('status')->default('pending');
            $table->json('data')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'invitable_type', 'invitable_id']);
            $table->index(['invitable_type', 'invitable_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_invitations');
    }
};
