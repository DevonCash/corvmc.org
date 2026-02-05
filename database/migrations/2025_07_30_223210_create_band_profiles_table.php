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
            $table->string('hometown')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('name')->unique();
            $table->text('bio')->nullable();
            $table->json('links')->nullable(); // Store social media links or other relevant URLs
            $table->json('contact')->nullable(); // Store contact information like email, phone, etc.
            $table->string('visibility')->default('private'); // Visibility of the profile, e.g., private, public

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('band_profile_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_profile_id')->constrained('band_profiles')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('status', ['active', 'invited', 'declined'])->default('active');
            $table->timestamp('invited_at')->nullable();
            $table->string('role')->default('member'); // e.g., 'member', 'admin'
            $table->string('position')->nullable(); // Position in the band, e.g., 'vocalist', 'guitarist'
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_profile_members');
        Schema::dropIfExists('band_profiles');
    }
};
