<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use App\Models\User;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class WalkInAction
{
    public static function make(): Action
    {
        return Action::make('walkIn')
            ->label('Walk-In')
            ->icon('tabler-user-plus')
            ->color('info')
            ->size('sm')
            ->schema([
                Select::make('user_id')
                    ->label('Volunteer')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id'))
                    ->required(),
            ])
            ->action(function (Shift $record, array $data) {
                $user = User::findOrFail($data['user_id']);
                app(HourLogService::class)->walkIn($user, $record);
                Notification::make()->title("{$user->name} checked in")->success()->send();
            });
    }
}
