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
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_title')->nullable();
            $table->text('staff_bio')->nullable();
            $table->enum('staff_type', ['board', 'staff'])->nullable();
            $table->integer('staff_sort_order')->nullable();
            $table->boolean('show_on_about_page')->default(false);
            $table->json('staff_social_links')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'staff_title',
                'staff_bio', 
                'staff_type',
                'staff_sort_order',
                'show_on_about_page',
                'staff_social_links'
            ]);
        });
    }
};
