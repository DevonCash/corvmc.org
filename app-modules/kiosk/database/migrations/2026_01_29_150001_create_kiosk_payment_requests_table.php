<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_device_id')
                ->constrained('kiosk_devices')
                ->cascadeOnDelete();
            $table->foreignId('target_device_id')
                ->constrained('kiosk_devices')
                ->cascadeOnDelete();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('quantity');
            $table->string('customer_email')->nullable();
            $table->boolean('is_sustaining_member')->default(false);
            $table->string('status')->default('pending');
            $table->string('payment_intent_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['target_device_id', 'status']);
            $table->index(['source_device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_payment_requests');
    }
};
