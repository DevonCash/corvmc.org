<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Add STI type column
            $table->string('type')->default('App\\Models\\RehearsalReservation')->after('id');
            $table->index('type');

            // Add polymorphic relationship columns
            $table->string('reservable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('reservable_id')->nullable()->after('reservable_type');
            $table->index(['reservable_type', 'reservable_id']);

            // Make user_id nullable temporarily for migration
            $table->foreignId('user_id')->nullable()->change();
        });

        // Migrate existing data: all current reservations are owned by users
        DB::table('reservations')->update([
            'type' => 'App\\Models\\RehearsalReservation',
            'reservable_type' => 'App\\Models\\User',
            'reservable_id' => DB::raw('user_id'),
        ]);

        // Now drop the old user_id column
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add user_id column
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained();
        });

        // Migrate data back (only for User-owned reservations)
        DB::table('reservations')
            ->where('reservable_type', 'App\\Models\\User')
            ->update([
                'user_id' => DB::raw('reservable_id'),
            ]);

        // Drop polymorphic and STI columns
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['reservable_type', 'reservable_id']);
            $table->dropColumn(['reservable_type', 'reservable_id', 'type']);
        });
    }
};
