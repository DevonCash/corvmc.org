<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSustainingMembers
{
    use AsAction;

    /**
     * Get all sustaining members (role-based only).
     */
    public function handle(): Collection
    {
        return Cache::remember('sustaining_members', 1800, function () {
            return User::whereHas('roles', function ($query) {
                $query->where('name', config('membership.member_role', 'sustaining member'));
            })->with(['profile', 'subscriptions'])->get();
        });
    }
}
