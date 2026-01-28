<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('state')->default('available')->after('status');
        });

        // Copy existing status values to state for initial migration
        DB::statement("UPDATE equipment SET state = CASE 
            WHEN status = 'available' THEN 'available'
            WHEN status = 'checked_out' THEN 'loaned' 
            WHEN status = 'maintenance' THEN 'maintenance'
            ELSE 'available'
        END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
