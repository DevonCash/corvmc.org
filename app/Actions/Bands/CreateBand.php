<?php

namespace App\Actions\Bands;

use App\Models\Band;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBand
{
    use AsAction;

    /**
     * Create a new band with proper validation and notifications.
     */
    public function handle(array $data): Band
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            if (!$user?->can('create', Band::class)) {
                throw new UnauthorizedException('User does not have permission to create bands.');
            }

            // Set owner to current user if not specified
            if (!isset($data['owner_id'])) {
                $data['owner_id'] = $user->id;
            }

            $band = Band::create($data);

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $band->attachTags($data['tags']);
            }

            // Add the creator as a member if they're not already
            if (!$band->memberships()->active()->where('user_id', $user->id)->exists()) {
                AddMember::run($band, $user, ['role' => 'owner']);
            }

            return $band;
        });
    }
}
