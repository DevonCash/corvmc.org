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
        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('parent_equipment_id')->nullable()->constrained('equipment')->onDelete('cascade');
            $table->boolean('can_lend_separately')->default(true);
            $table->boolean('is_kit')->default(false); // True for parent equipment that represents a kit
            $table->integer('sort_order')->default(0); // For ordering components within a kit
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropForeign(['parent_equipment_id']);
            $table->dropColumn(['parent_equipment_id', 'can_lend_separately', 'is_kit', 'sort_order']);
        });
    }
};
