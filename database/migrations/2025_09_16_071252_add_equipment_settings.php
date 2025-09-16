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
        \Spatie\LaravelSettings\Models\SettingsProperty::create([
            'group' => 'equipment',
            'name' => 'enable_rental_features',
            'payload' => json_encode(false),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Spatie\LaravelSettings\Models\SettingsProperty::where('group', 'equipment')->delete();
    }
};
