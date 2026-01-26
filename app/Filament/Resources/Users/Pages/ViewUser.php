<?php

namespace App\Filament\Resources\Users\Pages;

use CorvMC\Finance\Enums\CreditType;
use App\Filament\Resources\Users\UserResource;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property \App\Models\User $record
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('add_credits')
                ->label('Add Credits')
                ->icon('tabler-plus')
                ->color('success')
                ->visible(fn () => User::me()?->can('manage credits'))
                ->form([
                    Select::make('credit_type')
                        ->label('Credit Type')
                        ->options([
                            CreditType::FreeHours->value => 'Free Hours (Practice Space)',
                            CreditType::EquipmentCredits->value => 'Equipment Credits',
                        ])
                        ->default(CreditType::FreeHours->value)
                        ->required(),

                    TextInput::make('blocks')
                        ->label('Blocks to Add')
                        ->helperText('1 block = 30 minutes. Enter whole numbers only.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $component) {
                            $hours = round($state * 0.5, 1);
                            $component->helperText("1 block = 30 minutes. {$state} blocks = {$hours} hours.");
                        }),

                    Textarea::make('description')
                        ->label('Reason (Optional)')
                        ->helperText('Explain why credits are being added (will appear in transaction log)')
                        ->rows(2)
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $user = $this->record;
                    $creditType = CreditType::from($data['credit_type']);
                    $blocks = (int) $data['blocks'];
                    $hours = round($blocks * 0.5, 1);
                    $description = $data['description'] ?? 'Manual credit addition by admin';

                    $user->addCredit(
                        $blocks,
                        $creditType,
                        'admin_adjustment',
                        null,
                        $description
                    );

                    Notification::make()
                        ->title('Credits Added')
                        ->body("Added {$blocks} blocks ({$hours} hours) to {$user->name}'s account")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Add Credits')
                ->modalDescription(fn (array $data) => isset($data['blocks'])
                        ? "Add {$data['blocks']} blocks (".round($data['blocks'] * 0.5, 1)." hours) to this user's account?"
                        : "Add credits to this user's account?"
                ),

            Action::make('deduct_credits')
                ->label('Deduct Credits')
                ->icon('tabler-minus')
                ->color('danger')
                ->visible(fn () => User::me()?->can('manage credits'))
                ->form([
                    Select::make('credit_type')
                        ->label('Credit Type')
                        ->options([
                            CreditType::FreeHours->value => 'Free Hours (Practice Space)',
                            CreditType::EquipmentCredits->value => 'Equipment Credits',
                        ])
                        ->default(CreditType::FreeHours->value)
                        ->required(),

                    TextInput::make('blocks')
                        ->label('Blocks to Deduct')
                        ->helperText(function () {
                            $balance = $this->record->getCreditBalance(CreditType::FreeHours);
                            $hours = Reservation::blocksToHours($balance);

                            return "1 block = 30 minutes. Current balance: {$balance} blocks ({$hours} hours).";
                        })
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(fn () => max(1, $this->record->getCreditBalance(CreditType::FreeHours)))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $component) {
                            $hours = round($state * 0.5, 1);
                            $balance = $this->record->getCreditBalance(CreditType::FreeHours);
                            $remaining = max(0, $balance - $state);
                            $remainingHours = round($remaining * 0.5, 1);
                            $component->helperText("Deducting {$state} blocks ({$hours} hours). Remaining: {$remaining} blocks ({$remainingHours} hours).");
                        }),

                    Textarea::make('description')
                        ->label('Reason (Required)')
                        ->helperText('Explain why credits are being deducted (will appear in transaction log)')
                        ->rows(2)
                        ->maxLength(255)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $user = $this->record;
                    $creditType = CreditType::from($data['credit_type']);
                    $blocks = (int) $data['blocks'];
                    $hours = round($blocks * 0.5, 1);
                    $description = $data['description'];

                    $user->deductCredit(
                        $blocks,
                        $creditType,
                        'admin_adjustment',
                        null
                    );

                    Notification::make()
                        ->title('Credits Deducted')
                        ->body("Deducted {$blocks} blocks ({$hours} hours) from {$user->name}'s account")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Deduct Credits')
                ->modalDescription(fn (array $data) => isset($data['blocks'])
                        ? "Deduct {$data['blocks']} blocks (".round($data['blocks'] * 0.5, 1)." hours) from this user's account?"
                        : "Deduct credits from this user's account?"
                )
                ->modalSubmitActionLabel('Deduct Credits'),
        ];
    }
}
