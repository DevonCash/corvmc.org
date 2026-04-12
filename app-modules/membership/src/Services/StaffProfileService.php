<?php

namespace CorvMC\Membership\Services;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffProfileService
{
    public function create(array $data): StaffProfile
    {
        return StaffProfile::create($data);
    }

    public function update(StaffProfile $profile, array $data): StaffProfile
    {
        $profile->update($data);
        return $profile->fresh();
    }

    public function delete(StaffProfile $profile): bool
    {
        return $profile->delete();
    }

    public function linkToUser(StaffProfile $profile, User $user): void
    {
        $profile->update(['user_id' => $user->id]);
    }

    public function unlinkFromUser(StaffProfile $profile): void
    {
        $profile->update(['user_id' => null]);
    }

    public function toggleActiveStatus(StaffProfile $profile): StaffProfile
    {
        $profile->update(['is_active' => !$profile->is_active]);
        return $profile;
    }

    public function reorderStaffProfiles(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                StaffProfile::where('id', $id)->update(['order' => $index + 1]);
            }
        });
    }

    public function bulkUpdateProfiles(array $profileIds, array $data): int
    {
        return StaffProfile::whereIn('id', $profileIds)->update($data);
    }

    public function getStaffProfileStats(): array
    {
        return [
            'total_profiles' => StaffProfile::count(),
            'active_profiles' => StaffProfile::where('is_active', true)->count(),
            'linked_profiles' => StaffProfile::whereNotNull('user_id')->count(),
        ];
    }
}