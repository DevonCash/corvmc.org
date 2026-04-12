<?php

namespace CorvMC\Membership\Services;

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use Illuminate\Support\Facades\DB;

class BandService
{
    public function create(User $owner, array $data): Band
    {
        return DB::transaction(function () use ($owner, $data) {
            $band = Band::create($data);
            
            // Add owner as admin member
            $band->members()->attach($owner->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);
            
            return $band;
        });
    }

    public function update(Band $band, array $data): Band
    {
        $band->update($data);
        return $band->fresh();
    }

    public function delete(Band $band): bool
    {
        return $band->delete();
    }

    public function addMember(Band $band, User $user, string $role = 'member'): void
    {
        $band->members()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    public function removeMember(Band $band, User $user): void
    {
        $band->members()->detach($user->id);
    }

    public function updateMember(Band $band, User $user, array $data): void
    {
        $band->members()->updateExistingPivot($user->id, $data);
    }

    public function acceptInvitation($invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $this->addMember($invitation->band, $invitation->user);
            $invitation->update(['status' => 'accepted']);
        });
    }

    public function declineInvitation($invitation): void
    {
        $invitation->update(['status' => 'declined']);
    }

    public function cancelInvitation($invitation): void
    {
        $invitation->update(['status' => 'cancelled']);
    }
}