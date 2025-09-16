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
            $table->boolean('loanable')->default(true)->after('status');
        });
        
        // Set existing equipment as loanable based on current logic
        // CMC-owned equipment that's available should be loanable
        DB::statement("
            UPDATE equipment 
            SET loanable = CASE 
                WHEN ownership_status = 'cmc_owned' AND status = 'available' THEN true
                WHEN ownership_status = 'on_loan_to_cmc' AND status = 'available' THEN true
                ELSE false
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('loanable');
        });
    }
};
