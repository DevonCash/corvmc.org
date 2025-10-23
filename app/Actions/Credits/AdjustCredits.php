<?php

namespace App\Actions\Credits;

use App\Concerns\AsFilamentAction;
use App\Enums\CreditType;
use App\Models\User;
use Filament\Schemas\Components\Grid;
use Lorisleiva\Actions\Concerns\AsAction;


class AdjustCredits
{
    use AsAction;
    use AsFilamentAction;

    public function handle(User $user, int $amount, CreditType $creditType = CreditType::FreeHours): void
    {
        // Implementation to add credits to the user
        $user->addCredit($amount, $creditType, 'admin_adjustment');
    }

    public static function filamentAction(): \Filament\Actions\Action
    {
        return static::buildBaseAction()
            ->schema(function ($livewire) {
                $user = $livewire->ownerRecord;

                return [
                    Grid::make(3)->schema([
                        \Filament\Forms\Components\Placeholder::make('free_hours_label')
                            ->label('Free Hours')
                            ->content(fn () => $user->getCreditBalance(CreditType::FreeHours) . ' blocks'),
                        \Filament\Forms\Components\TextInput::make('free_hours_adjustment')
                            ->label('Adjustment')
                            ->numeric()
                            ->default(0)
                            ->step(1)
                            ->columnSpan(2),
                    ]),
                    Grid::make(3)->schema([
                        \Filament\Forms\Components\Placeholder::make('equipment_credits_label')
                            ->label('Equipment Credits')
                            ->content(fn () => $user->getCreditBalance(CreditType::EquipmentCredits) . ' blocks'),
                        \Filament\Forms\Components\TextInput::make('equipment_credits_adjustment')
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

                if (!empty($data['free_hours_adjustment']) && $data['free_hours_adjustment'] != 0) {
                    static::run($user, (int) $data['free_hours_adjustment'], CreditType::FreeHours);
                }

                if (!empty($data['equipment_credits_adjustment']) && $data['equipment_credits_adjustment'] != 0) {
                    static::run($user, (int) $data['equipment_credits_adjustment'], CreditType::EquipmentCredits);
                }
            });
    }
}
