<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetActiveStaffProfiles
{
    use AsAction;

    /**
     * Get active staff profiles ordered by sort order.
     */
    public function handle(?string $type = null): Collection
    {
        $query = StaffProfile::active()->ordered();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }
}
