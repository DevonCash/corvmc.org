<?php

namespace App\Actions\StaffProfiles;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBoardMembers
{
    use AsAction;

    /**
     * Get board members.
     */
    public function handle(): Collection
    {
        return StaffProfile::active()->board()->ordered()->get();
    }
}
