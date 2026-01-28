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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->morphs('reportable');
            $table->foreignId('reported_by_id')->constrained('users')->onDelete('cascade');
            $table->string('reason');
            $table->text('custom_reason')->nullable();
            $table->enum('status', ['pending', 'upheld', 'dismissed', 'escalated'])->default('pending');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            // Prevent duplicate reports from same user on same content
            $table->unique(['reportable_type', 'reportable_id', 'reported_by_id'], 'unique_user_content_report');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
