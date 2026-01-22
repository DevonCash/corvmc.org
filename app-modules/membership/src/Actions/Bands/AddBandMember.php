<?php

namespace CorvMC\Membership\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use CorvMC\Membership\Notifications\BandInvitationNotification;
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
     * Invite a CMC member to join a band.
     * Idempotent - can be called multiple times to resend invitation.
     */
    public function handle(
        Band $band,
        User $user,
        array $data = [],
    ): void {
        // Check if user is already an active member
        if ($band->memberships()->active()->where('user_id', $user->id)->exists()) {
            throw BandException::userAlreadyMember();
        }

        DB::transaction(function () use ($band, $user, $data) {
            // Check for existing invitation
            $existingInvitation = $band->memberships()->invited()->where('user_id', $user->id)->first();

            if ($existingInvitation) {
                // Resend invitation - update timestamp and data
                $existingInvitation->update([
                    ...$data,
                    'invited_at' => now(),
                ]);
            } else {
                // Create new invitation
                $band->members()->attach($user->id, [
                    ...$data,
                    'status' => 'invited',
                    'invited_at' => now(),
                ]);
            }
        });

        // Send notification outside transaction - user can still see invitation in UI if email fails
        try {
            $user->notify(new BandInvitationNotification(
                $band,
                $data['role'] ?? 'member',
                $data['position'] ?? null
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send band invitation notification', [
                'band_id' => $band->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
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
                                        $query->where('band_profile_id', $band->id)
                                            ->where('status', 'active');
                                    });
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                ->toArray();
                        }
                    )
                    ->getOptionLabelUsing(
                        fn ($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                    )
                    ->searchable()
                    ->helperText('Select a CMC member to invite'),

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

                $user = User::find($data['user_id']);

                // Check if this is a resend
                $isResend = $band->memberships()->invited()->where('user_id', $user->id)->exists();

                static::run($band, $user, $data);

                Notification::make()
                    ->title($isResend ? 'Invitation resent' : 'Invitation sent')
                    ->body(($isResend ? 'Invitation resent to ' : 'Invitation sent to ').$user->name)
                    ->success()
                    ->send();
            })
            ->authorize('create');
    }
}
