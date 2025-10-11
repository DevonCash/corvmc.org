<?php

namespace App\Filament\Resources\Bands\RelationManagers;

use App\Filament\Resources\Bands\Actions\AddBandMemberAction;
use App\Filament\Resources\Bands\Actions\EditBandMemberAction;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = "Band Members";
    protected static ?string $recordTitleAttribute = 'name';

    public function canViewAny(): bool
    {
        // Allow viewing if user can view the parent record
        return Auth::user()->can('view', $this->ownerRecord);
    }


    public function table(Table $table): Table
    {
        return $table

            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Member Name')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Active',
                        'invited' => 'Pending Invitation',
                        'declined' => 'Declined',
                        default => ucfirst($state),
                    }),

                TextColumn::make('position')
                    ->label('Position')
                    ->placeholder('No position set')
                    ->grow(true),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('show_declined')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!($data['show_declined'] ?? false)) {
                            return $query->whereNot('status', 'declined');
                        }
                        return $query;
                    })

            ])
            ->filtersLayout(FiltersLayout::BelowContent)
            ->headerActions([
                AddBandMemberAction::make($this->ownerRecord)
                    ->visible(fn(): bool => Auth::user()->can('update', $this->ownerRecord))

            ])
            ->recordActions([
                Action::make('accept_invitation')
                    ->label('Accept')
                    ->color('success')
                    ->icon('tabler-check')
                    ->requiresConfirmation()
                    ->modalHeading('Accept Band Invitation')
                    ->modalDescription(fn($record) => "Accept invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\AcceptInvitation::run($this->ownerRecord, $user);

                            Notification::make()
                                ->title('Invitation accepted')
                                ->body("Welcome to {$this->ownerRecord->name}!")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to accept invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === Auth::user()->id
                    ),

                Action::make('decline_invitation')
                    ->label('Decline')
                    ->color('danger')
                    ->icon('tabler-x')
                    ->requiresConfirmation()
                    ->modalHeading('Decline Band Invitation')
                    ->modalDescription(fn($record) => "Decline invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\DeclineInvitation::run($this->ownerRecord, $user);

                            Notification::make()
                                ->title('Invitation declined')
                                ->body('You have declined the invitation')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to decline invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === Auth::user()->id
                    ),

                Action::make('resend_invitation')
                    ->label('Resend')
                    ->color('warning')
                    ->icon('tabler-mail-forward')
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        \App\Actions\Bands\ResendInvitation::run($this->ownerRecord, $user);

                        Notification::make()
                            ->title('Invitation resent')
                            ->body("Invitation resent to {$record->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            Auth::user()->can('manageMembers', $this->ownerRecord)
                    ),

                Action::make('reinvite_declined')
                    ->label('Re-invite')
                    ->color('primary')
                    ->icon('tabler-mail')
                    ->requiresConfirmation()
                    ->modalHeading('Re-invite Member')
                    ->modalDescription(fn($record) => "Send a new invitation to {$record->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\InviteMember::run(
                                $this->ownerRecord,
                                $user,
                                $record->role ?? 'member',
                                $record->position,
                                $record->name
                            );

                            Notification::make()
                                ->title('Invitation sent')
                                ->body("New invitation sent to {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to send invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'declined' &&
                            Auth::user()->can('manageMembers', $this->ownerRecord)
                    ),

                EditBandMemberAction::make($this->ownerRecord),

                DeleteAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Band Member')
                    ->modalDescription('Are you sure you want to remove this member from the band?')
                    ->using(fn($record) => $record->delete())
                    ->visible(
                        fn($record): bool => Auth::user()->can('removeMembers', $this->ownerRecord) &&
                            $record->user_id !== $this->ownerRecord->owner_id // Can't remove owner
                    ),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
