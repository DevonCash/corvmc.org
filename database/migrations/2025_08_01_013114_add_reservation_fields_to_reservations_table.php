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
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('cost', 8, 2)->default(0)->after('status');
            $table->decimal('hours_used', 4, 2)->default(0)->after('cost');
            $table->boolean('is_recurring')->default(false)->after('hours_used');
            $table->json('recurrence_pattern')->nullable()->after('is_recurring');
            $table->text('notes')->nullable()->after('recurrence_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'cost',
                'hours_used',
                'is_recurring',
                'recurrence_pattern',
                'notes',
            ]);
        });
    }
};
