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
        Schema::create('band_profiles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('hometown')->nullable(); // New field for hometown
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name')->unique();
            $table->text('bio')->nullable();
            $table->jsonb('links')->nullable(); // Store social media links or other relevant URLs
            $table->jsonb('contact')->nullable(); // Store contact information like email, phone, etc.
            $table->string('visibility')->default('private'); // Visibility of the profile, e.g., private, public
        });

        Schema::create('band_profile_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_profile_id')->constrained('band_profiles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role')->default('member'); // e.g., 'member', 'admin'
            $table->string('position')->nullable(); // Position in the band, e.g., 'vocalist', 'guitarist'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_profiles');
        Schema::dropIfExists('band_profile_members');
    }
};
