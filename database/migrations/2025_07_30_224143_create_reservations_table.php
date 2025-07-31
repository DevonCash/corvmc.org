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
            $table->foreignId('production_id')->nullable()->constrained();
            $table->string('status')->default('pending'); // e.g., 'pending', 'confirmed', 'cancelled'
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
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('reservation_users');
    }
};
