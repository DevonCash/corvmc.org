<?php

namespace App\Filament\Actions\Credits;

use App\Models\User;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Facades\CreditService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;

class AdjustCreditsAction
{
    public static function make(): Action
    {
        return Action::make('adjust_credits')
            ->label('Adjust Credits')
            ->icon('tabler-plus-minus')
            ->color('primary')
            ->schema(function ($livewire) {
                $user = $livewire->ownerRecord;

                return [
                    Grid::make(3)->schema([
                        Placeholder::make('free_hours_label')
                            ->label('Free Hours')
                            ->content(fn() => $user->getCreditBalance(CreditType::FreeHours) . ' blocks'),
                        TextInput::make('free_hours_adjustment')
                            ->label('Adjustment')
                            ->numeric()
                            ->default(0)
                            ->step(1)
                            ->columnSpan(2),
                    ]),
                    Grid::make(3)->schema([
                        Placeholder::make('equipment_credits_label')
                            ->label('Equipment Credits')
                            ->content(fn() => $user->getCreditBalance(CreditType::EquipmentCredits) . ' blocks'),
                        TextInput::make('equipment_credits_adjustment')
                            ->label('Adjustment')
                            ->numeric()
                            ->default(0)
                            ->step(1)
                            ->columnSpan(2),
                    ]),
                ];
            })
            ->modalWidth('md')
            ->action(function (array $data, $livewire) {
                $user = $livewire->ownerRecord;

                if (! empty($data['free_hours_adjustment']) && $data['free_hours_adjustment'] != 0) {
                    CreditService::adjustCredits($user, (int) $data['free_hours_adjustment'], CreditType::FreeHours);
                }

                if (! empty($data['equipment_credits_adjustment']) && $data['equipment_credits_adjustment'] != 0) {
                    CreditService::adjustCredits($user, (int) $data['equipment_credits_adjustment'], CreditType::EquipmentCredits);
                }
            });
    }
}
