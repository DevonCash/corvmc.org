<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('review_reason');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null')->after('approval_notes');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestamp('queued_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'approval_notes']);
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason', 'queued_at']);
        });
    }
};
