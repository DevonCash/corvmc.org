<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAllStaffProfiles
{
    use AsAction;

    /**
     * Get all staff profiles ordered by sort order.
     */
    public function handle(): Collection
    {
        return StaffProfile::ordered()->get();
    }
}
