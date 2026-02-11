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
        Schema::table('resource_lists', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('resource_lists', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
        });
    }
};
