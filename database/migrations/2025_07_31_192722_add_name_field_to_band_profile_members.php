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
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change()->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change()->constrained()->onDelete('cascade');
        });
    }
};
