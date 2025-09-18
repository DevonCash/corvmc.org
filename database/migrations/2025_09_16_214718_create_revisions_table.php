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
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable'); // polymorphic relationship to any model
            $table->json('original_data'); // snapshot of original model data
            $table->json('proposed_changes'); // the proposed changes
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('submitted_by_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable(); // reason for approval/rejection
            $table->text('submission_reason')->nullable(); // why the submitter made these changes
            $table->string('revision_type')->default('update'); // update, create, delete
            $table->boolean('auto_approved')->default(false); // was this auto-approved by trust system
            $table->timestamps();
            
            // Note: morphs() already creates an index for revisionable_type, revisionable_id
            $table->index(['status', 'created_at']);
            $table->index(['submitted_by_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
