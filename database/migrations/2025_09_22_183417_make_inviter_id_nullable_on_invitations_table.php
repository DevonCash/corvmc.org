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
        Schema::table('invitations', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['inviter_id']);

            // Make inviter_id nullable
            $table->foreignId('inviter_id')->nullable()->change();

            // Re-add the foreign key constraint as nullable
            $table->foreign('inviter_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Drop the nullable foreign key constraint
            $table->dropForeign(['inviter_id']);

            // Make inviter_id required again
            $table->foreignId('inviter_id')->nullable(false)->change();

            // Re-add the original foreign key constraint
            $table->foreign('inviter_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
