<?php

namespace App\Filament\Actions\Payment;

use App\Filament\Shared\Actions\Action;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\CompData;
use CorvMC\Finance\Services\PaymentService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Action for marking any chargeable entity as comped.
 * 
 * This action handles the UI concerns for recording complimentary charges
 * and delegates business logic to the PaymentService.
 * Works with any model that implements the Chargeable interface.
 */
class ChargeableMarkCompedAction
{
    public static function make(): Action
    {
        return Action::make('mark_comped')
            ->label('Comp Charge')
            ->icon('tabler-gift')
            ->color('info')
            ->authorize('manage')
            ->visible(fn (Model $record) => 
                $record instanceof Chargeable && $record->needsPayment()
            )
            ->schema([
                Select::make('reason')
                    ->label('Comp Reason')
                    ->options([
                        'staff_discretion' => 'Staff Discretion',
                        'equipment_issue' => 'Equipment/Facility Issue',
                        'customer_service' => 'Customer Service',
                        'promotional' => 'Promotional',
                        'volunteer_benefit' => 'Volunteer Benefit',
                        'board_approved' => 'Board Approved',
                        'other' => 'Other',
                    ])
                    ->required(),
                TextInput::make('authorized_by')
                    ->label('Authorized By')
                    ->placeholder('Name of person authorizing comp')
                    ->default(fn () => auth()->user()->name),
                Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Additional details about why this was comped...')
                    ->rows(2),
            ])
            ->action(function (Model $record, array $data) {
                if (! $record instanceof Chargeable) {
                    return;
                }

                // Create DTO from form data
                $compData = CompData::from([
                    'chargeable' => $record,
                    'reason' => $data['reason'],
                    'authorizedBy' => $data['authorized_by'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Use service to record comp
                $service = app(PaymentService::class);
                $service->recordComp($compData);

                Notification::make()
                    ->title('Charge comped')
                    ->success()
                    ->send();
            });
    }

    /**
     * Bulk action for comping multiple chargeable entities.
     */
    public static function bulkAction(): Action
    {
        return Action::make('mark_comped_bulk')
            ->label('Comp Charges')
            ->icon('tabler-gift')
            ->color('info')
            ->authorize('manage')
            ->schema([
                Select::make('reason')
                    ->label('Comp Reason')
                    ->options([
                        'staff_discretion' => 'Staff Discretion',
                        'equipment_issue' => 'Equipment/Facility Issue',
                        'customer_service' => 'Customer Service',
                        'promotional' => 'Promotional',
                        'volunteer_benefit' => 'Volunteer Benefit',
                        'board_approved' => 'Board Approved',
                        'other' => 'Other',
                    ])
                    ->required(),
                TextInput::make('authorized_by')
                    ->label('Authorized By')
                    ->placeholder('Name of person authorizing comp')
                    ->default(fn () => auth()->user()->name),
                Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Additional details about why these were comped...')
                    ->rows(2),
            ])
            ->action(function (Collection $records, array $data) {
                $service = app(PaymentService::class);
                $count = 0;

                foreach ($records as $record) {
                    if ($record instanceof Chargeable && $record->needsPayment()) {
                        $compData = CompData::from([
                            'chargeable' => $record,
                            'reason' => $data['reason'],
                            'authorizedBy' => $data['authorized_by'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $service->recordComp($compData);
                        $count++;
                    }
                }

                Notification::make()
                    ->title("{$count} charges comped")
                    ->success()
                    ->send();
            });
    }
}