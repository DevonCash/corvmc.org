<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy phone numbers from member_profiles.contact->phone to users.phone
     * where the user doesn't already have a phone number set.
     */
    public function up(): void
    {
        DB::table('member_profiles')
            ->join('users', 'member_profiles.user_id', '=', 'users.id')
            ->whereNull('users.phone')
            ->whereNotNull('member_profiles.contact')
            ->orderBy('member_profiles.id')
            ->each(function ($row) {
                $contact = json_decode($row->contact, true);
                $phone = $contact['phone'] ?? null;

                if ($phone) {
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->update(['phone' => $phone]);
                }
            });
    }

    /**
     * This is a data migration - no rollback needed.
     */
    public function down(): void
    {
        // Not reversible: we can't distinguish which phones were copied vs already existed.
    }
};
