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
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('hometown')->nullable(); // New field for hometown
            $table->text('bio')->nullable();
            $table->json('links')->nullable(); // Store social media links or other relevant URLs
            $table->json('contact')->nullable(); // Store contact information like email, phone, etc.
            $table->string('visibility')->default('private'); // Visibility of the profile, e.g., private, public
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
