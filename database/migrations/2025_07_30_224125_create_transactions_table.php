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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->string('transaction_id')->unique();
            $table->string('email')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('type'); // e.g., 'donation', 'purchase'
            $table->json('response'); // Store the response from Zeffy

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
