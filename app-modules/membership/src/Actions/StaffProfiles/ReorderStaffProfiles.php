<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderStaffProfiles
{
    use AsAction;

    /**
     * Reorder staff profiles.
     */
    public function handle(array $staffProfileIds): bool
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
}
