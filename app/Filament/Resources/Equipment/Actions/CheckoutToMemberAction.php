<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Filament\Components\MemberSelector;
use App\Models\User;
use App\Services\EquipmentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CheckoutToMemberAction
{
    public static function make(): Action
    {
        return Action::make('checkout_to_member')
            ->label('Checkout')
            ->icon('tabler-user')
            ->color('success')
            ->modalWidth('md')
            ->modalHeading('Checkout Equipment')
            ->modalDescription('Check out this equipment to a member')
            ->schema([
                MemberSelector::make('borrower_id')
                    ->default(Auth::id())
                    ->visible(fn() => Auth::user()->can('manage equipment loans'))
                    ->required(),

                DateTimePicker::make('due_at')
                    ->label('Due Date & Time')
                    ->required()
                    ->default(now()->addWeeks(2))
                    ->minDate(now()->addDay())
                    ->displayFormat('M j, Y g:i A'),

                Select::make('condition_out')
                    ->label('Condition When Checked Out')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                    ])
                    ->default('good')
                    ->required(),

                TextEntry::make('security_deposit')
                    ->visible(fn($record) => $record->security_deposit !== null)
                    ->state(fn($record) =>  "\${$record->security_deposit}")
                    ->label('Security Deposit'),

                TextEntry::make('rental_fee')
                    ->visible(fn($record) => $record->rental_fee !== null)
                    ->state(fn($record) =>  "\${$record->rental_fee}")
                    ->label('Rental Fee'),

                Textarea::make('notes')
                    ->label('Checkout Notes')
                    ->placeholder('Any special instructions or conditions')
                    ->rows(3),
            ])
            ->action(function (array $data, $record) {
                try {
                    $borrower = User::find($data['borrower_id']);

                    $loan = \App\Actions\Equipment\CheckoutToMember::run(
                        equipment: $record,
                        borrower: $borrower,
                        dueDate: Carbon::parse($data['due_at']),
                        conditionOut: $data['condition_out'],
                        securityDeposit: (float) ($data['security_deposit'] ?? 0),
                        rentalFee: (float) ($data['rental_fee'] ?? 0),
                        notes: $data['notes'] ?? null
                    );

                    Notification::make()
                        ->title('Equipment Checked Out')
                        ->body("Successfully checked out {$record->name} to {$borrower->name}")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Checkout Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-user')
            ->visible(fn($record) => $record->is_available);
    }
}
