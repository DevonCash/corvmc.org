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
        Schema::create('community_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();

            // Basic event information
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();

            // Venue information
            $table->string('venue_name');
            $table->text('venue_address');
            $table->decimal('distance_from_corvallis', 5, 2)->nullable(); // Distance in minutes

            // Event categorization
            $table->string('event_type')->default('performance'); // performance, workshop, open_mic, etc.

            // Approval workflow
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->string('visibility')->default('public'); // public, members_only
            $table->timestamp('published_at')->nullable();

            // Organizer and trust system
            $table->foreignId('organizer_id')->constrained('users');
            $table->integer('trust_points')->default(0);
            $table->boolean('auto_approved')->default(false);

            // Ticketing
            $table->string('ticket_url')->nullable();
            $table->decimal('ticket_price', 8, 2)->nullable();

            // Indexes for performance
            $table->index(['status', 'start_time']);
            $table->index(['organizer_id', 'status']);
            $table->index(['visibility', 'published_at']);
            $table->index('distance_from_corvallis');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_events');
    }
};
