<?php

namespace CorvMC\Membership\Actions\StaffProfiles;

use CorvMC\Membership\Models\StaffProfile;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateProfiles
{
    use AsAction;

    /**
     * Bulk update staff profiles.
     */
    public function handle(array $profileIds, array $data): int
    {
        // TODO: Add proper authorization check
        // if (!Auth::check() || !Auth::user()->can('bulkUpdate', StaffProfile::class)) {
        //     throw new \Exception("Unauthorized to bulk update profiles");
        // }
        return StaffProfile::whereIn('id', $profileIds)->update($data);
    }
}
