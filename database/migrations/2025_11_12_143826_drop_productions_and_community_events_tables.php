<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old production tables
        Schema::dropIfExists('production_bands');
        Schema::dropIfExists('productions');

        // Drop community events table
        Schema::dropIfExists('community_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - Productions and CommunityEvents have been replaced by Events
    }
};
