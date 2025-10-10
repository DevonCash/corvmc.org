<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffMembers
{
    use AsAction;

    /**
     * Get staff members.
     */
    public function handle(): Collection
    {
        return StaffProfile::active()->staff()->ordered()->get();
    }
}
