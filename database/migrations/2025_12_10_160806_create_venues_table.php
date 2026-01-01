<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create venues table to replace the LocationData JSON DTO.
     * Structure based on VenueLocationData with Google Maps integration.
     */
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('address');
            $table->string('city')->default('Corvallis');
            $table->string('state', 2)->default('OR');
            $table->string('zip', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('distance_from_corvallis')->nullable()->comment('Driving time in minutes');
            $table->timestamp('distance_cached_at')->nullable();
            $table->boolean('is_cmc')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
