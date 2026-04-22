<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_type')->comment('Finance product-type string');
            $table->unsignedBigInteger('product_id')->nullable()->comment('Model ID for model-backed products');
            $table->string('description');
            $table->string('unit')->comment('hour, ticket, fee, discount, etc.');
            $table->integer('unit_price')->comment('Cents per unit');
            $table->decimal('quantity', 8, 2);
            $table->integer('amount')->comment('Cents — negative for discounts');
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_items');
    }
};
