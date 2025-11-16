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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->dateTime('doors_time')->nullable();
            $table->json('location')->nullable(); // Assuming location can be complex, using JSON
            $table->string('ticket_url')->nullable();
            $table->decimal('ticket_price', 8, 2)->nullable();
            $table->string('status')->default('pre-production');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('manager_id')->constrained('users');
        });

        Schema::create('production_bands', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->default(0); // Order of the band in the production
            $table->integer('set_length')->nullable(); // Length of the band's set in minutes
            $table->foreignId('production_id')->constrained('productions')->onDelete('cascade');
            $table->foreignId('band_profile_id')->constrained('band_profiles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_bands');
        Schema::dropIfExists('productions');
    }
};
