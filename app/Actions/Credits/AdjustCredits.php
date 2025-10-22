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
            ->schema([
                \Filament\Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Grid::make(2)->schema([
                    \Filament\Forms\Components\Select::make('credit_type')
                        ->label('Credit Type')
                        ->default(CreditType::FreeHours->value)
                        ->options([
                            CreditType::FreeHours->value => 'Free Hours',
                            CreditType::EquipmentCredits->value => 'Equipment Credits',
                        ])
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->step(1)
                        ->required(),
                ])
            ])
            ->modalWidth('md')
            ->action(function (array $data) {
                $user = User::findOrFail($data['user_id']);
                $creditType = CreditType::from($data['credit_type']);
                $amount = $data['amount'];

                static::run($user, $amount, $creditType);
            });
    }
}
