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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['guitar', 'bass', 'amplifier', 'microphone', 'percussion', 'recording', 'specialty']);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('description')->nullable();
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor', 'needs_repair']);

            // Acquisition tracking
            $table->enum('acquisition_type', ['donated', 'loaned_to_us', 'purchased']);
            $table->foreignId('provider_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('provider_contact')->nullable(); // ContactData for external providers
            $table->date('acquisition_date');
            $table->date('return_due_date')->nullable(); // For items loaned to CMC
            $table->text('acquisition_notes')->nullable();
            $table->enum('ownership_status', ['cmc_owned', 'on_loan_to_cmc', 'returned_to_owner'])->default('cmc_owned');

            // Current status and location
            $table->enum('status', ['available', 'checked_out', 'maintenance', 'retired'])->default('available');
            $table->decimal('estimated_value', 8, 2)->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['type', 'status']);
            $table->index(['acquisition_type', 'provider_id']);
            $table->index('ownership_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
