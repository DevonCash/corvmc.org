<?php

namespace CorvMC\Sponsorship\Services;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing sponsor relationships and sponsored memberships.
 * 
 * This service handles the assignment and revocation of sponsored memberships
 * as well as sponsor slot availability management.
 */
class SponsorshipService
{
    /**
     * Assign a sponsored membership to a user.
     *
     * @param Sponsor $sponsor The sponsor providing the membership
     * @param User $user The user receiving the membership
     * @throws \Exception if sponsor has no available slots or user is already sponsored
     */
    public function assignMembership(Sponsor $sponsor, User $user): void
    {
        // Check if sponsor has available slots
        if (! $sponsor->hasAvailableSlots()) {
            throw new \Exception(
                "Cannot assign sponsored membership: {$sponsor->name} has no available slots. ".
                "{$sponsor->usedSlots()} of {$sponsor->sponsored_memberships} slots are in use."
            );
        }

        // Check if user is already sponsored by this sponsor
        if ($sponsor->sponsoredMembers()->where('user_id', $user->id)->exists()) {
            throw new \Exception(
                "Cannot assign sponsored membership: {$user->name} is already sponsored by {$sponsor->name}."
            );
        }

        DB::transaction(function () use ($sponsor, $user) {
            $sponsor->sponsoredMembers()->attach($user->id);
        });
    }

    /**
     * Revoke a sponsored membership from a user.
     *
     * @param Sponsor $sponsor The sponsor revoking the membership
     * @param User $user The user losing the membership
     * @throws \Exception if user is not sponsored by this sponsor
     */
    public function revokeMembership(Sponsor $sponsor, User $user): void
    {
        // Check if user is sponsored by this sponsor
        if (! $sponsor->sponsoredMembers()->where('user_id', $user->id)->exists()) {
            throw new \Exception(
                "Cannot revoke sponsored membership: {$user->name} is not sponsored by {$sponsor->name}."
            );
        }

        DB::transaction(function () use ($sponsor, $user) {
            $sponsor->sponsoredMembers()->detach($user->id);
        });
    }

    /**
     * Get sponsor's slot allocation details.
     *
     * @param Sponsor $sponsor The sponsor to check
     * @return array{total: int, used: int, available: int, has_available: bool}
     */
    public function getAvailableSlots(Sponsor $sponsor): array
    {
        $total = $sponsor->sponsored_memberships;
        $used = $sponsor->usedSlots();
        $available = $sponsor->availableSlots();

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'has_available' => $sponsor->hasAvailableSlots(),
        ];
    }

    /**
     * Check if a user is sponsored by any sponsor.
     *
     * @param User $user The user to check
     * @return bool
     */
    public function isUserSponsored(User $user): bool
    {
        return Sponsor::whereHas('sponsoredMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->exists();
    }

    /**
     * Get the sponsor for a user if they have one.
     *
     * @param User $user The user to check
     * @return Sponsor|null
     */
    public function getUserSponsor(User $user): ?Sponsor
    {
        return Sponsor::whereHas('sponsoredMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();
    }
}