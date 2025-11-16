<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description');
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('reported'); // reported, in_progress, waiting_parts, completed, cancelled
            $table->string('priority')->default('normal'); // low, normal, high, urgent

            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->text('repair_notes')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['equipment_id', 'status']);
            $table->index(['assigned_to_id', 'status']);
            $table->index(['severity', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_damage_reports');
    }
};
