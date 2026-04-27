<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('band_profile_members');
        $hasStatus = in_array('status', $columns);
        $hasInvitedAt = in_array('invited_at', $columns);

        if ($hasStatus) {
            $indexes = Schema::getIndexListing('band_profile_members');

            if (in_array('idx_band_members_status', $indexes)) {
                Schema::table('band_profile_members', function (Blueprint $table) {
                    $table->dropIndex('idx_band_members_status');
                });
            }

            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if ($hasInvitedAt) {
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->dropColumn('invited_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->timestamp('invited_at')->nullable();
        });

        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->index('status', 'idx_band_members_status');
        });
    }
};
