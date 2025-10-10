<?php

namespace App\Actions\MemberProfiles;

use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProfilesWithFlag
{
    use AsAction;

    /**
     * Get profiles with specific flags.
     */
    public function handle(string $flag, ?User $viewingUser = null): Collection
    {
        $query = MemberProfile::withFlag($flag);

        // Apply visibility filter
        if (!$viewingUser) {
            $query->where('visibility', 'public');
        } elseif (!$viewingUser->can('view private member profiles')) {
            $query->where(function ($q) use ($viewingUser) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $viewingUser->id)
                    ->orWhere('visibility', 'members');
            });
        }

        return $query->with(['user', 'tags'])->get();
    }
}
