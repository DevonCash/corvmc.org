<?php

namespace App\Filament\Staff\Resources\Sponsors\RelationManagers;

use CorvMC\Sponsorship\Actions\AssignSponsoredMembership;
use CorvMC\Sponsorship\Actions\RevokeSponsoredMembership;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SponsoredMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'sponsoredMembers';

    protected static ?string $title = 'Sponsored Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.created_at')
                    ->label('Sponsored Since')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Sponsored Member')
                    ->color('primary')
                    ->icon('tabler-user-plus')
                    ->recordSelect(
                        fn (Select $select) => $select
                            ->label('CMC Member')
                            ->placeholder('Search for a member...')
                            ->getSearchResultsUsing(function (string $search): array {
                                return User::where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                })
                                    ->whereDoesntHave('sponsors', function ($query) {
                                        $query->where('sponsors.id', $this->ownerRecord->id);
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(
                                fn ($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                            )
                            ->searchable()
                            ->required()
                    )
                    ->action(function (array $data): void {
                        $sponsor = $this->ownerRecord;
                        $user = User::find($data['recordId']);

                        try {
                            AssignSponsoredMembership::run($sponsor, $user);

                            Notification::make()
                                ->title('Sponsored member added')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to assign sponsored membership')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Add Sponsored Member')
                    ->modalDescription(function () {
                        $sponsor = $this->ownerRecord;
                        $available = $sponsor->availableSlots();
                        $used = $sponsor->usedSlots();
                        $total = $sponsor->sponsored_memberships;

                        return "{$available} of {$total} sponsorship slots available ({$used} in use)";
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->before(function (DetachAction $action, $record): void {
                        $sponsor = $this->ownerRecord;

                        try {
                            RevokeSponsoredMembership::run($sponsor, $record);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to revoke sponsored membership')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->after(function (): void {
                        Notification::make()
                            ->title('Sponsored membership revoked')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Remove Sponsored Member')
                    ->modalDescription('Are you sure you want to revoke this sponsored membership?'),
            ])
            ->defaultSort('pivot.created_at', 'desc');
    }
}
