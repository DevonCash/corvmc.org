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
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('start_time', 'start_datetime');
            $table->renameColumn('end_time', 'end_datetime');
            $table->renameColumn('doors_time', 'doors_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('start_datetime', 'start_time');
            $table->renameColumn('end_datetime', 'end_time');
            $table->renameColumn('doors_datetime', 'doors_time');
        });
    }
};
