<?php

namespace App\Filament\Actions\Bands;

use App\Models\User;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Services\BandService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class UpdateBandMemberAction
{
    public static function make(): Action
    {
        return Action::make('update_member')
            ->label('Edit')
            ->icon('tabler-edit')
            ->color('warning')
            ->modalHeading(fn (?Model $record) => $record instanceof BandMember
                ? "Edit {$record->user->name}"
                : 'Edit Member')
            ->modalWidth('md')
            ->visible(function (?Model $record) {
                if (! $record instanceof BandMember || $record->status !== 'active') {
                    return false;
                }

                $user = User::me();

                return $user && ($record->band->isOwner($user) || $record->band->isAdmin($user));
            })
            ->authorize(fn (?Model $record) => auth()->user()?->can('update', $record))
            ->fillForm(fn (?Model $record) => $record instanceof BandMember ? [
                'role' => $record->role,
                'position' => $record->position,
            ] : [])
            ->schema([
                Select::make('role')
                    ->label('Role')
                    ->options([
                        'member' => 'Member',
                        'admin' => 'Admin',
                    ])
                    ->required(),

                TextInput::make('position')
                    ->label('Position/Instrument')
                    ->placeholder('e.g., Lead Guitar, Vocals, Drums')
                    ->maxLength(255),
            ])
            ->action(function (array $data, Model $record) {
                app(BandService::class)->updateMember(
                    $record->band,
                    $record->user,
                    $data
                );

                Notification::make()
                    ->title('Member updated')
                    ->body("{$record->user->name}'s details have been updated.")
                    ->success()
                    ->send();
            });
    }
}
