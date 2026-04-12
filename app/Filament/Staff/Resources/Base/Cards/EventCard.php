<?php

namespace App\Filament\Staff\Resources\Base\Cards;

use App\Filament\Staff\Resources\Productions\ProductionResource;
use CorvMC\Events\Models\Event;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;

class EventCard
{
    /**
     * Create a standardized event/production card section
     */
    public static function make(string $relationship = 'event', ?string $label = null): Section
    {
        return Section::make($label ?? 'Event')
            ->icon('tabler-calendar-event')
            ->compact()
            ->headerActions([
                \Filament\Actions\Action::make('view_event')
                    ->hiddenLabel()
                    ->icon('tabler-arrow-right')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn(?Model $record) => $record?->{$relationship} ? ProductionResource::getUrl('edit', ['record' => $record->{$relationship}]) : null)
                    ->openUrlInNewTab(),
            ])
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextEntry::make("{$relationship}.title")
                            ->hiddenLabel()
                            ->weight(FontWeight::Bold)
                            ->size('large'),
                        Flex::make([
                            TextEntry::make("{$relationship}.start_time")
                                ->hiddenLabel()
                                ->icon('tabler-calendar')
                                ->iconColor('gray')
                                ->date('M j, Y'),
                            TextEntry::make("{$relationship}.start_time")
                                ->hiddenLabel()
                                ->icon('tabler-clock')
                                ->iconColor('gray')
                                ->time('g:i A'),
                        ]),
                        TextEntry::make("{$relationship}.venue")
                            ->hiddenLabel()
                            ->icon('tabler-map-pin')
                            ->iconColor('gray')
                            ->placeholder('Venue TBD'),
                        Flex::make([
                            TextEntry::make("{$relationship}.status")
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make("{$relationship}.ticketed")
                                ->hiddenLabel()
                                ->badge()
                                ->color('success')
                                ->state('Ticketed')
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->ticketed),
                            TextEntry::make("{$relationship}.all_ages")
                                ->hiddenLabel()
                                ->badge()
                                ->color('info')
                                ->state('All Ages')
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->all_ages),
                        ]),
                        TextEntry::make("{$relationship}.ticket_stats")
                            ->hiddenLabel()
                            ->icon('tabler-ticket')
                            ->iconColor('gray')
                            ->state(fn(?Model $record) => self::getTicketStats($record?->{$relationship}))
                            ->visible(fn(?Model $record) => $record?->{$relationship}?->ticketed),
                    ]),
            ]);
    }

    /**
     * Create a compact event reference (for inline use)
     */
    public static function makeCompact(string $relationship = 'event'): Flex
    {
        return Flex::make([
            Grid::make(1)
                ->gap(0)
                ->schema([
                    TextEntry::make("{$relationship}.title")
                        ->hiddenLabel()
                        ->weight('bold'),
                    Flex::make([
                        TextEntry::make("{$relationship}.start_time")
                            ->hiddenLabel()
                            ->icon('tabler-calendar')
                            ->iconColor('gray')
                            ->size('small')
                            ->date('M j'),
                        TextEntry::make("{$relationship}.start_time")
                            ->hiddenLabel()
                            ->icon('tabler-clock')
                            ->iconColor('gray')
                            ->size('small')
                            ->time('g:i A'),
                        TextEntry::make("{$relationship}.status")
                            ->hiddenLabel()
                            ->badge()
                            ->size('small'),
                    ]),
                ]),
        ])->verticallyAlignCenter();
    }

    /**
     * Get ticket statistics for an event
     */
    private static function getTicketStats(?Event $event): ?string
    {
        if (!$event || !$event->ticketed) {
            return null;
        }

        $sold = $event->tickets()->count();
        $capacity = $event->capacity;

        if ($capacity) {
            $percentage = round(($sold / $capacity) * 100);
            return "{$sold}/{$capacity} sold ({$percentage}%)";
        }

        return "{$sold} sold";
    }
}