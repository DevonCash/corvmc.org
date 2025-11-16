<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class CheckoutKitComponentsAction
{
    public static function make(): Action
    {
        return Action::make('checkout_kit_components')
            ->label('Checkout Components')
            ->icon('tabler-category')
            ->color('primary')
            ->modalWidth('lg')
            ->modalHeading('Checkout Kit Components')
            ->modalDescription('Check out selected components from this kit')
            ->schema([
                CheckboxList::make('component_ids')
                    ->label('Select Components to Check Out')
                    ->options(
                        fn ($record) => $record->children
                            ->where('status', 'available')
                            ->where('can_lend_separately', true)
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->descriptions(
                        fn ($record) => $record->children
                            ->where('status', 'available')
                            ->where('can_lend_separately', true)
                            ->mapWithKeys(fn ($component) => [
                                $component->id => collect([$component->brand, $component->model, $component->condition])
                                    ->filter()
                                    ->join(' • '),
                            ])
                            ->toArray()
                    )
                    ->required()
                    ->minItems(1)
                    ->columns(2),

                Select::make('borrower_id')
                    ->label('Member')
                    ->relationship('borrower', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Select a member'),

                DateTimePicker::make('due_at')
                    ->label('Due Date & Time')
                    ->required()
                    ->default(now()->addWeeks(2))
                    ->minDate(now()->addDay())
                    ->displayFormat('M j, Y g:i A'),

                Select::make('condition_out')
                    ->label('General Condition')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                    ])
                    ->default('good')
                    ->required(),

                TextInput::make('security_deposit')
                    ->label('Security Deposit (Total)')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->default(0),

                TextInput::make('rental_fee')
                    ->label('Rental Fee (Total)')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->default(0),

                Textarea::make('notes')
                    ->label('Checkout Notes')
                    ->placeholder('Any special instructions or conditions')
                    ->rows(3),
            ])
            ->action(function (array $data, $record) {
                $borrower = User::find($data['borrower_id']);
                $componentIds = $data['component_ids'];
                $components = $record->children()->whereIn('id', $componentIds)->get();

                $checkedOutCount = 0;
                $totalDeposit = (float) ($data['security_deposit'] ?? 0);
                $totalFee = (float) ($data['rental_fee'] ?? 0);
                $depositPerItem = count($components) > 0 ? $totalDeposit / count($components) : 0;
                $feePerItem = count($components) > 0 ? $totalFee / count($components) : 0;

                foreach ($components as $component) {
                    if (! $component->is_available || ! $component->can_lend_separately) {
                        continue;
                    }

                    \App\Actions\Equipment\CheckoutToMember::run(
                        equipment: $component,
                        borrower: $borrower,
                        dueDate: Carbon::parse($data['due_at']),
                        conditionOut: $data['condition_out'],
                        securityDeposit: $depositPerItem,
                        rentalFee: $feePerItem,
                        notes: $data['notes'] ?? null
                    );

                    $checkedOutCount++;
                }

                if ($checkedOutCount > 0) {
                    Notification::make()
                        ->title('Kit Components Checked Out')
                        ->body("Successfully checked out {$checkedOutCount} components to {$borrower->name}")
                        ->success()
                        ->send();
                } else {
                    throw new \Exception('No components were available for checkout.');
                }
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-category')
            ->visible(
                fn ($record) => $record->is_kit &&
                    $record->children
                        ->where('status', 'available')
                        ->where('can_lend_separately', true)
                        ->isNotEmpty()
            );
    }
}
