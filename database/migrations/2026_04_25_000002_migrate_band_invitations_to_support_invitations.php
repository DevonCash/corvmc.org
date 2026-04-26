<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate BandMember rows with status='invited' to support_invitations
        $invitedMembers = DB::table('band_profile_members')
            ->where('status', 'invited')
            ->get();

        foreach ($invitedMembers as $member) {
            DB::table('support_invitations')->insert([
                'inviter_id' => null,
                'user_id' => $member->user_id,
                'invitable_type' => 'band',
                'invitable_id' => $member->band_profile_id,
                'status' => 'pending',
                'data' => json_encode(array_filter([
                    'role' => $member->role,
                    'position' => $member->position,
                ])),
                'responded_at' => null,
                'created_at' => $member->invited_at ?? $member->created_at,
                'updated_at' => $member->updated_at,
            ]);
        }

        // Delete the migrated rows — they were invitation placeholders, not active memberships
        DB::table('band_profile_members')
            ->where('status', 'invited')
            ->delete();
    }

    public function down(): void
    {
        // Restore invitation records back to band_profile_members
        $invitations = DB::table('support_invitations')
            ->where('invitable_type', 'band')
            ->where('status', 'pending')
            ->get();

        foreach ($invitations as $invitation) {
            $data = json_decode($invitation->data, true) ?? [];

            DB::table('band_profile_members')->insert([
                'user_id' => $invitation->user_id,
                'band_profile_id' => $invitation->invitable_id,
                'role' => $data['role'] ?? 'member',
                'position' => $data['position'] ?? null,
                'status' => 'invited',
                'invited_at' => $invitation->created_at,
                'created_at' => $invitation->created_at,
                'updated_at' => $invitation->updated_at,
            ]);
        }

        DB::table('support_invitations')
            ->where('invitable_type', 'band')
            ->where('status', 'pending')
            ->delete();
    }
};
