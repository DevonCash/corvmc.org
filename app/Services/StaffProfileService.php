<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\StaffProfileType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffProfileService
{
    /**
     * Create a new staff profile.
     */
    public function createStaffProfile(array $data): StaffProfile
    {
        return DB::transaction(function () use ($data) {
            $data['type'] = $data['type'] ?? StaffProfileType::Staff;
            $staffProfile = StaffProfile::create($data);

            // Handle profile image upload if provided
            if (isset($data['profile_image'])) {
                $staffProfile->addMediaFromRequest('profile_image')
                    ->toMediaCollection('profile_image');
            }

            return $staffProfile;
        });
    }

    /**
     * Update a staff profile.
     */
    public function updateStaffProfile(StaffProfile $staffProfile, array $data): StaffProfile
    {
        $user = Auth::user();
        foreach ($data as $key => $value) {
            // TODO: Add proper policy check
            // if (!$user?->can('updateField', [$staffProfile, $key])) {
            //     throw new \Exception("cannot modify restricted fields");
            // }
        }

        return DB::transaction(function () use ($staffProfile, $data) {
            $staffProfile->update($data);

            // Handle profile image upload if provided
            if (isset($data['profile_image'])) {
                $staffProfile->clearMediaCollection('profile_image');
                $staffProfile->addMediaFromRequest('profile_image')
                    ->toMediaCollection('profile_image');
            }

            return $staffProfile->fresh();
        });
    }

    /**
     * Delete a staff profile.
     */
    public function deleteStaffProfile(StaffProfile $staffProfile): bool
    {
        return DB::transaction(function () use ($staffProfile) {
            // Clear all media
            $staffProfile->clearMediaCollection('profile_image');

            return $staffProfile->delete();
        });
    }

    public function getOrganizedProfiles(): array
    {
        return StaffProfile::active()
            ->ordered()
            ->get()
            ->groupBy('type')
            ->toArray();
    }

    /**
     * Get active staff profiles ordered by sort order.
     */
    public function getActiveStaffProfiles(?string $type = null): Collection
    {
        $query = StaffProfile::active()->ordered();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    public function getAllStaffProfiles(): Collection
    {
        return StaffProfile::ordered()->get();
    }

    /**
     * Get board members.
     */
    public function getBoardMembers(): Collection
    {
        return StaffProfile::active()->board()->ordered()->get();
    }

    /**
     * Get staff members.
     */
    public function getStaffMembers(): Collection
    {
        return StaffProfile::active()->staff()->ordered()->get();
    }

    /**
     * Reorder staff profiles.
     */
    public function reorderStaffProfiles(array $staffProfileIds): bool
    {
        return DB::transaction(function () use ($staffProfileIds) {
            // Handle both formats:
            // 1. Sequential array: [id1, id2, id3] -> assigns sort_order 1, 2, 3
            // 2. Associative array: [id1 => order1, id2 => order2] -> assigns specified orders

            if (array_keys($staffProfileIds) === range(0, count($staffProfileIds) - 1)) {
                // Sequential array format
                foreach ($staffProfileIds as $index => $staffProfileId) {
                    StaffProfile::where('id', $staffProfileId)
                        ->update(['sort_order' => $index + 1]); // 1-based sort order
                }
            } else {
                // Associative array format
                foreach ($staffProfileIds as $staffProfileId => $sortOrder) {
                    StaffProfile::where('id', $staffProfileId)
                        ->update(['sort_order' => $sortOrder]);
                }
            }

            return true;
        });
    }

    /**
     * Toggle active status.
     */
    public function toggleActiveStatus(StaffProfile $staffProfile): bool
    {
        return $staffProfile->update([
            'is_active' => !$staffProfile->is_active
        ]);
    }

    /**
     * Get staff profile statistics.
     */
    public function getStaffProfileStats(): array
    {
        $typeCounts = StaffProfile::select('type', DB::raw('count(*) as count'))
            ->where('is_active', true)
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_profiles' => StaffProfile::count(),
            'active_profiles' => StaffProfile::active()->count(),
            'inactive_profiles' => StaffProfile::inactive()->count(),
            'board_members' => $typeCounts['board'] ?? 0,
            'staff_members' => $typeCounts['staff'] ?? 0,
            'by_type' => [
                'board' => $typeCounts['board'] ?? 0,
                'staff' => $typeCounts['staff'] ?? 0,
            ],
        ];
    }

    /**
     * Link staff profile to user account.
     */
    public function linkToUser(StaffProfile $staffProfile, User $user): bool
    {
        return $staffProfile->update(['user_id' => $user->id]);
    }

    /**
     * Unlink staff profile from user account.
     */
    public function unlinkFromUser(StaffProfile $staffProfile): bool
    {
        return $staffProfile->update(['user_id' => null]);
    }


    public function bulkUpdateProfiles(array $profileIds, array $data): int
    {
        // TODO: Add proper authorization check
        // if (!Auth::check() || !Auth::user()->can('bulkUpdate', StaffProfile::class)) {
        //     throw new \Exception("Unauthorized to bulk update profiles");
        // }
        return StaffProfile::whereIn('id', $profileIds)->update($data);
    }
}
