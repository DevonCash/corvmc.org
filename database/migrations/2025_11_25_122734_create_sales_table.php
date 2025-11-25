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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('subtotal'); // in cents
            $table->integer('tax')->default(0); // in cents
            $table->integer('total'); // in cents
            $table->string('payment_method'); // SalePaymentMethod enum
            $table->string('status'); // SaleStatus enum
            $table->integer('tendered_amount')->nullable(); // in cents, for cash
            $table->integer('change_amount')->nullable(); // in cents, for cash
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index('payment_method');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
