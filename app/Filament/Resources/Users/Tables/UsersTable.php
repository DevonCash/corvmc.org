<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\InviteUserAction;
use App\Services\UserInvitationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),

                TagsColumn::make('roles.name')
                    ->label('Roles')
                    ->separator(','),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('email_verified')
                    ->options([
                        'verified' => 'Verified',
                        'unverified' => 'Unverified',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'verified') {
                            $query->whereNotNull('email_verified_at');
                        } elseif ($data['value'] === 'unverified') {
                            $query->whereNull('email_verified_at');
                        }
                    }),

                SelectFilter::make('invitation_status')
                    ->label('Invitation Status')
                    ->options([
                        'invited' => 'Invited (Pending)',
                        'registered' => 'Registered',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'invited') {
                            $query->whereNull('email_verified_at')
                                ->where('name', 'Invited User');
                        } elseif ($data['value'] === 'registered') {
                            $query->where(function ($q) {
                                $q->whereNotNull('email_verified_at')
                                    ->orWhere('name', '!=', 'Invited User');
                            });
                        }
                    }),

                SelectFilter::make('staff_filter')
                    ->label('Staff Members')
                    ->options([
                        'staff' => 'All Staff',
                        'board' => 'Board Members',
                        'staff_only' => 'Staff Members',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'staff') {
                            $query->where('show_on_about_page', true);
                        } elseif ($data['value'] === 'board') {
                            $query->where('staff_type', 'board')
                                  ->where('show_on_about_page', true);
                        } elseif ($data['value'] === 'staff_only') {
                            $query->where('staff_type', 'staff')
                                  ->where('show_on_about_page', true);
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('resend_invitation')
                    ->label('Resend Invitation')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record) => $record->email_verified_at === null && $record->name === 'Invited User')
                    ->requiresConfirmation()
                    ->modalHeading('Resend Invitation')
                    ->modalDescription(fn ($record) => "Resend invitation email to {$record->email}?")
                    ->action(function ($record) {
                        $invitationService = app(UserInvitationService::class);

                        if ($invitationService->resendInvitation($record)) {
                            Notification::make()
                                ->title('Invitation resent')
                                ->body("Invitation email has been resent to {$record->email}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to resend')
                                ->body('This user has already completed their registration')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('cancel_pending_invitations')
                        ->label('Cancel Pending Invitations')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Pending Invitations')
                        ->modalDescription('Are you sure you want to cancel the selected pending invitations? This will delete the invited users who have not yet registered.')
                        ->action(function (Collection $records) {
                            $invitationService = app(UserInvitationService::class);
                            $canceledCount = 0;

                            foreach ($records as $record) {
                                if ($invitationService->cancelInvitation($record)) {
                                    $canceledCount++;
                                }
                            }

                            if ($canceledCount > 0) {
                                Notification::make()
                                    ->title('Invitations canceled')
                                    ->body("Successfully canceled {$canceledCount} pending invitation(s)")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('No invitations canceled')
                                    ->body('Selected users have already completed their registration')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
