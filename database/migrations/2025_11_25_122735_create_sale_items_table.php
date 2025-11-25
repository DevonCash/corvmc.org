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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('sellable'); // Polymorphic to Product, Event, etc. (auto-creates index)
            $table->integer('quantity');
            $table->integer('unit_price'); // in cents
            $table->integer('subtotal'); // in cents
            $table->string('description'); // Snapshot of item name at purchase time
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
