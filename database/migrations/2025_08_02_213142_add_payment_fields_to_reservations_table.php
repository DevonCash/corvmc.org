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
            $table->string('payment_status')->default('unpaid')->after('cost'); // 'unpaid', 'paid', 'comped', 'refunded'
            $table->string('payment_method')->nullable()->after('payment_status'); // 'cash', 'card', 'venmo', 'comp', etc.
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->text('payment_notes')->nullable()->after('paid_at'); // admin notes about payment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_method', 
                'paid_at',
                'payment_notes',
            ]);
        });
    }
};
