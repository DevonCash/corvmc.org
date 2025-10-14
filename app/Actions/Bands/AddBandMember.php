<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Flex;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddBandMember
{
    use AsAction;

    /**
     * Add a member directly to a band (without invitation).
     */
    public function handle(
        Band $band,
        ?User $user = null,
        array $data = [],
    ): void {
        if ($band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        if ($band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyInvited();
        }

        DB::transaction(function () use ($band, $user, $data) {
            // Add invitation to pivot table
            $band->members()->attach($user->id, [
                ...$data,
                'status' => 'invited',
                'invited_at' => now(),
            ]);
        });
        // Send notification
        $user->notify(new BandInvitationNotification($band, $data['role'] ?? 'member', $data['position'] ?? null));
    }

    public static function filamentAction(): Action
    {
        return Action::make('invite')
            ->label('Add Member')
            ->color('primary')
            ->icon('tabler-user-plus')
            ->modalWidth('xl')
            ->schema([
                Select::make('user_id')
                    ->label('CMC Member')
                    ->required()
                    ->getSearchResultsUsing(
                        function (string $search, $livewire): array {
                            // Get the band record from the livewire component
                            $band = $livewire->record ?? $livewire->ownerRecord ?? null;

                            return User::where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                                ->when($band, function ($query) use ($band) {
                                    $query->whereDoesntHave('bands', function ($query) use ($band) {
                                        $query->where('band_profile_id', $band->id);
                                    });
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->email})"])
                                ->toArray();
                        }
                    )
                    ->getOptionLabelUsing(
                        fn($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                    )
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if ($state) {
                            $user = User::find($state);
                            if ($user) {
                                $set('name', $user->name);
                            }
                        }
                    })
                    ->helperText('Select an existing CMC member'),

                TextInput::make('name')
                    ->label('Name')
                    ->placeholder('Stage name')
                    ->maxLength(255)
                    ->required()
                    ->helperText('Their name for the band'),

                Flex::make([
                    Select::make('role')
                        ->label('Role')
                        ->options([
                            'member' => 'Member',
                            'admin' => 'Admin',
                        ])
                        ->grow(false)
                        ->default('member')
                        ->required(),

                    TextInput::make('position')
                        ->grow(true)
                        ->columnSpan(3)
                        ->label('Position')
                        ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                        ->maxLength(100),
                ]),
            ])
            ->action(function (array $data, $livewire): void {
                // Get the band from either page or relation manager context
                $band = $livewire->record ?? $livewire->ownerRecord;

                // Existing CMC member - send invitation
                $user = User::find($data['user_id']);

                static::run($band, $user, $data);

                Notification::make()
                    ->title('Invitation sent')
                    ->body("Invitation sent to {$user->name}")
                    ->success()
                    ->send();
            })
            ->authorize('create');
    }
}
