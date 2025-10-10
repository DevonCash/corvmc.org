<?php

namespace App\Filament\Resources\CommunityEvents\Pages;

use App\Filament\Resources\CommunityEvents\CommunityEventResource;
use App\Models\CommunityEvent;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewCommunityEvent extends ViewRecord
{
    protected static string $resource = CommunityEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('approve')
                ->icon('tabler-check')
                ->color('success')
                ->visible(fn (CommunityEvent $record): bool => 
                    $record->status === CommunityEvent::STATUS_PENDING && 
                    Auth::user()->can('approve community events'))
                ->requiresConfirmation()
                ->action(function (CommunityEvent $record) {
                    $record->update([
                        'status' => CommunityEvent::STATUS_APPROVED,
                        'published_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Event approved successfully')
                        ->success()
                        ->send();

                    return redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Actions\Action::make('reject')
                ->icon('tabler-x')
                ->color('danger')
                ->visible(fn (CommunityEvent $record): bool => 
                    $record->status === CommunityEvent::STATUS_PENDING && 
                    Auth::user()->can('approve community events'))
                ->requiresConfirmation()
                ->action(function (CommunityEvent $record) {
                    $record->update(['status' => CommunityEvent::STATUS_REJECTED]);

                    Notification::make()
                        ->title('Event rejected')
                        ->warning()
                        ->send();

                    return redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Event Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('poster_url')
                                    ->label('Poster')
                                    ->size(200),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('title')
                                            ->size('lg')
                                            ->weight('bold'),

                                        TextEntry::make('description')
                                            ->markdown(),

                                        TextEntry::make('event_type')
                                            ->badge()
                                            ->color('primary'),
                                    ])
                                    ->columnSpan(2),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('start_time')
                                    ->label('Start Time')
                                    ->dateTime('M j, Y g:i A'),

                                TextEntry::make('end_time')
                                    ->label('End Time')
                                    ->dateTime('M j, Y g:i A'),
                            ]),
                    ]),

                Section::make('Venue Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('venue_name')
                                    ->label('Venue'),

                                TextEntry::make('visibility')
                                    ->badge()
                                    ->colors([
                                        'success' => CommunityEvent::VISIBILITY_PUBLIC,
                                        'info' => CommunityEvent::VISIBILITY_MEMBERS_ONLY,
                                    ]),
                            ]),

                        TextEntry::make('venue_address')
                            ->columnSpanFull(),

                        TextEntry::make('distance_from_corvallis')
                            ->label('Distance from Corvallis')
                            ->formatStateUsing(function ($state) {
                                if ($state === null) return 'Unknown';
                                if ($state == 0) return 'Local (CMC)';
                                
                                $hours = floor($state / 60);
                                $minutes = $state % 60;
                                
                                if ($hours > 0) {
                                    return sprintf('%d hr %d min drive', $hours, $minutes);
                                }
                                return sprintf('%d min drive', $minutes);
                            })
                            ->color(function ($state) {
                                if ($state === null) return 'gray';
                                if ($state <= 30) return 'success';
                                if ($state <= 60) return 'warning';
                                return 'danger';
                            })
                            ->badge(),
                    ]),

                Section::make('Organizer Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('organizer.name')
                                    ->label('Organizer'),

                                TextEntry::make('organizer_trust_level')
                                    ->label('Trust Level')
                                    ->formatStateUsing(function (CommunityEvent $record) {
                                        $badge = $record->getOrganizerTrustBadge();
                                        return $badge ? $badge['label'] : $record->getOrganizerTrustLevel();
                                    })
                                    ->badge()
                                    ->color(function (CommunityEvent $record) {
                                        $badge = $record->getOrganizerTrustBadge();
                                        return $badge ? $badge['color'] : 'gray';
                                    }),
                            ]),

                        TextEntry::make('organizer_trust_points')
                            ->label('Trust Points')
                            ->formatStateUsing(function (CommunityEvent $record) {
                                return \App\Actions\Trust\GetTrustBalance::run($record->organizer, 'App\\Models\\CommunityEvent');
                            }),
                    ]),

                Section::make('Ticketing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ticket_price_display')
                                    ->label('Price'),

                                TextEntry::make('ticket_url')
                                    ->label('Tickets')
                                    ->url(null)
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->visible(fn (CommunityEvent $record): bool => $record->hasTickets()),

                Section::make('Administration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->colors([
                                        'warning' => CommunityEvent::STATUS_PENDING,
                                        'success' => CommunityEvent::STATUS_APPROVED,
                                        'danger' => CommunityEvent::STATUS_REJECTED,
                                        'gray' => CommunityEvent::STATUS_CANCELLED,
                                    ]),

                                TextEntry::make('published_at')
                                    ->label('Published At')
                                    ->dateTime('M j, Y g:i A')
                                    ->placeholder('Not published'),

                                TextEntry::make('reports_count')
                                    ->label('Reports')
                                    ->counts('reports')
                                    ->badge()
                                    ->color('danger'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Submitted At')
                                    ->dateTime('M j, Y g:i A'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M j, Y g:i A'),
                            ]),
                    ])
                    ->visible(fn () => Auth::user()->can('view admin info')),
            ]);
    }
}