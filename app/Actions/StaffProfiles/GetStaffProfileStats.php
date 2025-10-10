<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffProfileStats
{
    use AsAction;

    /**
     * Get staff profile statistics.
     */
    public function handle(): array
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
}
