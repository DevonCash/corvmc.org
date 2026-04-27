<?php

namespace CorvMC\Membership\Services;

use App\Models\User;
use CorvMC\Bands\Events\BandCreated;
use CorvMC\Bands\Events\BandUpdated;
use CorvMC\Bands\Events\BandDeleted;
use CorvMC\Bands\Models\Band;
use CorvMC\Support\Models\Invitation;
use CorvMC\Support\Services\InvitationService;
use Illuminate\Support\Facades\DB;

class BandService
{
    public function __construct(
        private InvitationService $invitationService,
    ) {}

    public function create(User $owner, array $data): Band
    {
        $band = DB::transaction(function () use ($owner, $data) {
            $band = Band::create(array_merge(['status' => 'active'], $data, ['owner_id' => $owner->id]));

            // Add owner as admin member
            $band->members()->attach($owner->id, [
                'role' => 'owner',
            ]);

            return $band;
        });

        BandCreated::dispatch($band);

        return $band;
    }

    public function update(Band $band, array $data): Band
    {
        $oldValues = collect($data)->mapWithKeys(fn ($v, $k) => [$k => $band->getOriginal($k)])->toArray();
        $band->update($data);

        BandUpdated::dispatch($band, array_keys($data), $oldValues);

        return $band->fresh();
    }

    public function delete(Band $band): bool
    {
        $result = $band->delete();

        BandDeleted::dispatch($band);

        return $result;
    }

    public function addMember(Band $band, User $user, string $role = 'member'): void
    {
        $band->members()->attach($user->id, [
            'role' => $role,
        ]);
    }

    public function inviteMember(Band $band, User $user, string $role = 'member', ?string $position = null): Invitation
    {
        return $this->invitationService->invite(
            subject: $band,
            invitee: $user,
            inviter: auth()->user(),
            data: array_filter([
                'role' => $role,
                'position' => $position,
            ]),
        );
    }

    public function removeMember(Band $band, User $user): void
    {
        $band->members()->detach($user->id);
    }

    public function updateMember(Band $band, User $user, array $data): void
    {
        $band->members()->updateExistingPivot($user->id, $data);
    }

    public function acceptInvitation(Invitation $invitation): void
    {
        $this->invitationService->accept($invitation);
    }

    public function declineInvitation(Invitation $invitation): void
    {
        $this->invitationService->decline($invitation);
    }

    public function retractInvitation(Invitation $invitation): void
    {
        $this->invitationService->retract($invitation);
    }
}