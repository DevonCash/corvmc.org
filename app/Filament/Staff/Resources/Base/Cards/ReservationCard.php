<?php

namespace App\Filament\Staff\Resources\Base\Cards;

use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;

class ReservationCard
{
    /**
     * Create a standardized reservation card section
     */
    public static function make(string $relationship = 'reservation', ?string $label = null): Section
    {
        return Section::make($label ?? 'Reservation')
            ->icon('tabler-calendar-time')
            ->compact()
            ->headerActions([
                \Filament\Actions\Action::make('view_reservation')
                    ->hiddenLabel()
                    ->icon('tabler-arrow-right')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn(?Model $record) => $record?->{$relationship} ? SpaceManagementResource::getUrl('view', ['record' => $record->{$relationship}]) : null)
                    ->openUrlInNewTab(),
            ])
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextEntry::make("{$relationship}.title")
                            ->hiddenLabel()
                            ->weight(FontWeight::Bold)
                            ->placeholder(fn(?Model $record) => $record?->{$relationship}?->getDisplayTitle()),
                        Flex::make([
                            TextEntry::make("{$relationship}.reserved_at")
                                ->hiddenLabel()
                                ->icon('tabler-calendar')
                                ->iconColor('gray')
                                ->date('M j, Y'),
                            TextEntry::make('time_slot')
                                ->hiddenLabel()
                                ->icon('tabler-clock')
                                ->iconColor('gray')
                                ->state(fn(?Model $record) => self::getTimeSlot($record?->{$relationship})),
                        ]),
                        Flex::make([
                            TextEntry::make("{$relationship}.status")
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make("{$relationship}.duration")
                                ->hiddenLabel()
                                ->badge()
                                ->color('gray')
                                ->state(fn(?Model $record) => self::formatHours($record?->{$relationship}?->duration)),
                            TextEntry::make("{$relationship}.free_hours")
                                ->hiddenLabel()
                                ->badge()
                                ->color('success')
                                ->state(function (?Model $record) use ($relationship): ?string {
                                    $reservation = $record?->{$relationship};
                                    if (! $reservation) {
                                        return null;
                                    }
                                    $order = \CorvMC\Finance\Facades\Finance::findActiveOrder($reservation);
                                    if (! $order) {
                                        return null;
                                    }
                                    $freeHours = $order->lineItems->filter->isDiscount()->sum(fn($li) => abs((float) $li->quantity));

                                    return self::formatHours($freeHours) . ' free';
                                })
                                ->visible(function (?Model $record) use ($relationship): bool {
                                    $reservation = $record?->{$relationship};
                                    if (! $reservation) {
                                        return false;
                                    }
                                    $order = \CorvMC\Finance\Facades\Finance::findActiveOrder($reservation);
                                    if (! $order) {
                                        return false;
                                    }

                                    return $order->lineItems->filter->isDiscount()->isNotEmpty();
                                }),
                        ]),
                    ]),
            ]);
    }

    /**
     * Create a compact reservation reference (for inline use)
     */
    public static function makeCompact(string $relationship = 'reservation'): Flex
    {
        return Flex::make([
            Grid::make(1)
                ->gap(0)
                ->schema([
                    TextEntry::make("{$relationship}.title")
                        ->hiddenLabel()
                        ->weight('bold')
                        ->placeholder(fn(?Model $record) => $record?->{$relationship}?->getDisplayTitle()),
                    Flex::make([
                        TextEntry::make("{$relationship}.reserved_at")
                            ->hiddenLabel()
                            ->icon('tabler-calendar')
                            ->iconColor('gray')
                            ->size('small')
                            ->date('M j'),
                        TextEntry::make('time_slot')
                            ->hiddenLabel()
                            ->icon('tabler-clock')
                            ->iconColor('gray')
                            ->size('small')
                            ->state(fn(?Model $record) => self::getTimeSlot($record?->{$relationship})),
                        TextEntry::make("{$relationship}.status")
                            ->hiddenLabel()
                            ->badge()
                            ->size('small'),
                    ]),
                ]),
        ])->verticallyAlignCenter();
    }

    /**
     * Get formatted time slot
     */
    private static function getTimeSlot(?Reservation $reservation): ?string
    {
        if (!$reservation) {
            return null;
        }

        return $reservation->reserved_at->format('g:i A') . ' – ' . 
               $reservation->reserved_until->format('g:i A') . ' (' . 
               self::formatHours($reservation->duration) . ')';
    }

    /**
     * Format hours consistently
     */
    protected static function formatHours(?float $hours): string
    {
        if (!$hours) {
            return '0 hrs';
        }

        return fmod($hours, 1) === 0.0 ? intval($hours) . ' hrs' : number_format($hours, 1) . ' hrs';
    }
}