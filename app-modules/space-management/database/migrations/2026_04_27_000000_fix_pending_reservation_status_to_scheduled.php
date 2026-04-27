<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any legacy 'pending' status values to 'scheduled'
        DB::table('reservations')
            ->where('status', 'pending')
            ->update(['status' => 'scheduled']);

        // Update the column default from 'pending' to 'scheduled'
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
