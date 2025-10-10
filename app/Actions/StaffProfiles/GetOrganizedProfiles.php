<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrganizedProfiles
{
    use AsAction;

    /**
     * Get staff profiles organized by type.
     */
    public function handle(): array
    {
        return StaffProfile::active()
            ->ordered()
            ->get()
            ->groupBy('type')
            ->toArray();
    }
}
