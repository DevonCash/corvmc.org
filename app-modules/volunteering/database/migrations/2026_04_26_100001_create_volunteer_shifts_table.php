<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('volunteer_positions');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('capacity');
            $table->timestamps();

            // event_id FK is intentionally not constrained here.
            // The relationship is resolved in the integration layer via
            // resolveRelationUsing to keep this module decoupled from Events.

            $table->index('event_id');
            $table->index('position_id');
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_shifts');
    }
};
