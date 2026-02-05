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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();

            // Basic event information
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->dateTime('doors_time')->nullable();

            // Location (JSON - supports LocationData DTO for CMC/external venues)
            $table->json('location')->nullable();

            // Links and ticketing
            $table->string('event_link')->nullable(); // Primary link (tickets, info, etc)
            $table->string('ticket_url')->nullable(); // Kept for backward compatibility
            $table->decimal('ticket_price', 8, 2)->nullable();

            // Publishing workflow
            $table->timestamp('published_at')->nullable();

            // Community calendar fields (for future use)
            $table->foreignId('organizer_id')->nullable()->constrained('users');
            $table->string('status')->default('approved'); // approved, cancelled
            $table->string('visibility')->default('public'); // public, members_only
            $table->string('event_type')->nullable(); // performance, workshop, open_mic, etc.
            $table->decimal('distance_from_corvallis', 5, 2)->nullable();
            $table->integer('trust_points')->default(0);
            $table->boolean('auto_approved')->default(false);

            // Indexes for performance
            $table->index(['status', 'start_time']);
            $table->index(['visibility', 'published_at']);
            $table->index('organizer_id');
        });

        // Performer/band lineup for events
        Schema::create('event_bands', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->default(0);
            $table->integer('set_length')->nullable(); // minutes
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('band_profile_id')->constrained('band_profiles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_bands');
        Schema::dropIfExists('events');
    }
};
