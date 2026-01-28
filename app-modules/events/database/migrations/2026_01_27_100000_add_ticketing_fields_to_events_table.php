<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add ticketing fields to events table.
     *
     * Enables native CMC ticketing for events as an alternative to external ticket URLs.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('ticketing_enabled')->default(false)->after('ticket_price');
            $table->unsignedInteger('ticket_quantity')->nullable()->after('ticketing_enabled')->comment('Total tickets available, null = unlimited');
            $table->unsignedBigInteger('ticket_price_override')->nullable()->after('ticket_quantity')->comment('Override global price in cents');
            $table->unsignedInteger('tickets_sold')->default(0)->after('ticket_price_override')->comment('Cached count of sold tickets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'ticketing_enabled',
                'ticket_quantity',
                'ticket_price_override',
                'tickets_sold',
            ]);
        });
    }
};
