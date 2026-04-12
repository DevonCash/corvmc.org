<?php

namespace App\Filament\Staff\Resources\Charges\Pages;

use App\Filament\Staff\Resources\Base\BaseViewRecord;
use App\Filament\Staff\Resources\Base\Cards\EventCard;
use App\Filament\Staff\Resources\Base\Cards\ReservationCard;
use App\Filament\Staff\Resources\Base\Cards\UserCard;
use App\Filament\Staff\Resources\Charges\ChargeResource;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use App\Filament\Infolists\Entries\MorphTypeEntry;
use App\Filament\Infolists\Entries\MorphEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;

class ViewCharge extends BaseViewRecord
{
    protected static string $resource = ChargeResource::class;

    public function getTitle(): string
    {
        return "Charge #{$this->record->id}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $this->buildStandardLayout($schema);
    }

    protected function getHeroComponents(): array
    {
        return [];
    }

    protected function getMainContentComponents(): array
    {
        $components = [];

        $components[] =  Flex::make([
            // Charge ID and Type
            MorphTypeEntry::make('chargeable')
                ->hiddenLabel()
                ->size(TextSize::Large),

            // Amount details
            Flex::make([
                TextEntry::make('amount')
                    ->hiddenLabel()
                    ->label('Gross')
                    ->prefix('Gross: ')
                    ->weight(FontWeight::Bold),
                TextEntry::make('net_amount')
                    ->hiddenLabel()
                    ->prefix('Net: ')
                    ->weight(FontWeight::Bold),
            ]),

            // Status badges
            Flex::make([
                TextEntry::make('status')
                    ->hiddenLabel()
                    ->badge()
                    ->size(TextSize::Large),
                TextEntry::make('payment_method')
                    ->hiddenLabel()
                    ->badge()
                    ->color('gray')
                    ->size(TextSize::Large)
                    ->visible(fn(?Charge $record) => $record->payment_method !== null),
            ]),
        ])->from('md')->columnSpanFull()->verticallyAlignCenter();

        // User card
        $components[] = UserCard::make('user', 'User');

        // Chargeable item - using MorphEntry to show the linked record
        $components[] = Section::make('Related Item')
            ->icon('tabler-link')
            ->compact()
            ->schema([
                Grid::make(2)->schema([
                    MorphTypeEntry::make('chargeable')
                        ->label('Type'),
                    MorphEntry::make('chargeable')
                        ->label('Record'),
                ]),
            ]);

        // Payment details
        $components[] = Section::make('Payment Details')
            ->icon('tabler-receipt')
            ->compact()
            ->collapsible()
            ->columnSpanFull()
            ->schema([
                Grid::make(['default' => 2, 'lg' => 3])->schema([
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                    TextEntry::make('paid_at')
                        ->label('Paid')
                        ->dateTime()
                        ->placeholder('Not paid'),
                    TextEntry::make('stripe_session_id')
                        ->label('Stripe Session')
                        ->copyable()
                        ->placeholder('—'),
                ]),
            ]);

        // Credits applied
        if ($this->record->credits_applied && count($this->record->credits_applied) > 0) {
            // $components[] = Section::make('Credits Applied')
            //     ->icon('tabler-gift')
            //     ->compact()
            //     ->collapsible()
            //     ->columnSpanFull()
            //     ->schema([
            //         TextEntry::make('credits_applied')
            //             ->hiddenLabel()
            //             ->formatStateUsing(function (array $state) {
            //                 $parts = [];
            //                 foreach ($state as $type => $blocks) {
            //                     $hours = $blocks * 0.5;
            //                     $parts[] = "{$type}: {$blocks} blocks ({$hours} hours)";
            //                 }
            //                 return implode(', ', $parts);
            //             }),
            //     ]);
        }

        // Notes
        if ($this->record->notes) {
            $components[] = Section::make('Notes')
                ->icon('tabler-notes')
                ->compact()
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('notes')
                        ->hiddenLabel()
                        ->markdown(),
                ]);
        }

        return $components;
    }

    protected function getHeaderActions(): array
    {
        /** @var Charge $record */
        $record = $this->record;

        return [
            Action::make('markPaid')
                ->label('Mark as Paid')
                ->icon('tabler-coin')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $record->status->isPending())
                ->action(function () use ($record) {
                    $record->markAsPaid('manual', null, null, 'Marked paid by staff');
                    Notification::make()->title('Charge marked as paid')->success()->send();
                    $this->refreshFormData(['status', 'paid_at', 'payment_method']);
                }),

            Action::make('markComped')
                ->label('Comp')
                ->icon('tabler-gift')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn() => $record->status->isPending())
                ->action(function () use ($record) {
                    $record->markAsComped('Comped by staff');
                    Notification::make()->title('Charge comped')->success()->send();
                    $this->refreshFormData(['status', 'paid_at']);
                }),

            Action::make('markRefunded')
                ->label('Refund')
                ->icon('tabler-receipt-refund')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will mark the charge as refunded. If this was a Stripe payment, you should also process the refund in the Stripe dashboard.')
                ->visible(fn() => $record->status->isPaid())
                ->action(function () use ($record) {
                    $record->markAsRefunded('Refunded by staff');
                    Notification::make()->title('Charge refunded')->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
