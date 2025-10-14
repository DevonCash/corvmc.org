<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBand
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Create a new band with proper validation and notifications.
     */
    public function handle(array $data): Band
    {
        return DB::transaction(function () use ($data) {
            if (!User::me()?->can('create', Band::class)) {
                throw new UnauthorizedException('User does not have permission to create bands.');
            }

            // Set owner to current user if not specified
            if (!isset($data['owner_id'])) {
                $data['owner_id'] = User::me()->id;
            }

            $band = Band::create($data);

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $band->attachTags($data['tags']);
            }

            // Add the creator as a member if they're not already
            if (!$band->memberships()->active()->where('user_id', User::me()->id)->exists()) {
                AddBandMember::run($band, User::me(), ['role' => 'owner']);
            }

            return $band;
        });
    }

    public static function filamentAction(): Action
    {
        return Action::make('create')
            ->label('Create Band')
            ->icon('tabler-plus')
            ->modalHeading('Create New Band')
            ->modalWidth('md')
            ->schema([
                TextInput::make('name')
                    ->label('Band Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter your band name')
                    ->autofocus(),
            ])
            ->action(function (array $data, $livewire) {
                // Check for claimable bands with the same name first
                $claimableBand = FindClaimableBand::run($data['name']);

                if ($claimableBand && User::me()?->can('claim', $claimableBand)) {
                    // Redirect to claiming workflow instead of creating duplicate
                    session()->flash('claimable_band', [
                        'id' => $claimableBand->id,
                        'name' => $claimableBand->name,
                        'data' => $data
                    ]);

                    $livewire->redirect(BandResource::getUrl('claim'));
                    return;
                }

                // Create the band
                $band = static::run($data);

                // Redirect to edit page
                $livewire->redirect(BandResource::getUrl('edit', ['record' => $band]));
            })
            ->authorize('create');
    }
}
