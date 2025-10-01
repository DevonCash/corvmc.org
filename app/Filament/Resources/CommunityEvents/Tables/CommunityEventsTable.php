<?php

namespace App\Filament\Resources\CommunityEvents\Tables;

use App\Models\CommunityEvent;
use App\Services\TrustService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CommunityEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('poster_url')
                    ->label('Poster')
                    ->circular()
                    ->imageSize(60),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (CommunityEvent $record): string => $record->venue_name),

                TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable()
                    ->description(function (CommunityEvent $record) {
                        $badge = $record->getOrganizerTrustBadge();
                        return $badge ? $badge['label'] : 'New organizer';
                    }),

                TextColumn::make('start_time')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn (CommunityEvent $record): string => $record->date_range),

                BadgeColumn::make('status')
                    ->colors([
                        'warning' => CommunityEvent::STATUS_PENDING,
                        'success' => CommunityEvent::STATUS_APPROVED,
                        'danger' => CommunityEvent::STATUS_REJECTED,
                        'gray' => CommunityEvent::STATUS_CANCELLED,
                    ]),

                BadgeColumn::make('event_type')
                    ->label('Type')
                    ->colors([
                        'primary' => CommunityEvent::TYPE_PERFORMANCE,
                        'info' => CommunityEvent::TYPE_WORKSHOP,
                        'warning' => CommunityEvent::TYPE_OPEN_MIC,
                        'success' => CommunityEvent::TYPE_COLLABORATIVE_SHOW,
                        'danger' => CommunityEvent::TYPE_ALBUM_RELEASE,
                    ]),

                TextColumn::make('distance_from_corvallis')
                    ->label('Distance')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) return 'Unknown';
                        if ($state == 0) return 'Local (CMC)';

                        $hours = floor($state / 60);
                        $minutes = $state % 60;

                        if ($hours > 0) {
                            return sprintf('%d hr %d min', $hours, $minutes);
                        }
                        return sprintf('%d min', $minutes);
                    })
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        if ($state <= 30) return 'success';
                        if ($state <= 60) return 'warning';
                        return 'danger';
                    }),

                TextColumn::make('visibility')
                    ->badge()
                    ->colors([
                        'success' => CommunityEvent::VISIBILITY_PUBLIC,
                        'info' => CommunityEvent::VISIBILITY_MEMBERS_ONLY,
                    ]),

                TextColumn::make('reports_count')
                    ->label('Reports')
                    ->counts('reports')
                    ->badge()
                    ->color('danger')
                    ->visible(fn () => Auth::user()?->can('view reports')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        CommunityEvent::STATUS_PENDING => 'Pending',
                        CommunityEvent::STATUS_APPROVED => 'Approved',
                        CommunityEvent::STATUS_REJECTED => 'Rejected',
                        CommunityEvent::STATUS_CANCELLED => 'Cancelled',
                    ]),

                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        CommunityEvent::TYPE_PERFORMANCE => 'Performance',
                        CommunityEvent::TYPE_WORKSHOP => 'Workshop',
                        CommunityEvent::TYPE_OPEN_MIC => 'Open Mic',
                        CommunityEvent::TYPE_COLLABORATIVE_SHOW => 'Collaborative Show',
                        CommunityEvent::TYPE_ALBUM_RELEASE => 'Album Release',
                    ]),

                SelectFilter::make('visibility')
                    ->options([
                        CommunityEvent::VISIBILITY_PUBLIC => 'Public',
                        CommunityEvent::VISIBILITY_MEMBERS_ONLY => 'Members Only',
                    ]),

                Filter::make('local_events')
                    ->label('Local Events Only')
                    ->query(fn (Builder $query): Builder => $query->local()),

                Filter::make('requires_approval')
                    ->label('Pending Approval')
                    ->query(fn (Builder $query): Builder => $query->where('status', CommunityEvent::STATUS_PENDING)),

                Filter::make('upcoming')
                    ->label('Upcoming Events')
                    ->query(fn (Builder $query): Builder => $query->where('start_time', '>', now())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('approve')
                    ->icon('tabler-check')
                    ->color('success')
                    ->visible(fn (CommunityEvent $record): bool => $record->status === CommunityEvent::STATUS_PENDING)
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
                    }),

                Action::make('reject')
                    ->icon('tabler-x')
                    ->color('danger')
                    ->visible(fn (CommunityEvent $record): bool => $record->status === CommunityEvent::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (CommunityEvent $record) {
                        $record->update(['status' => CommunityEvent::STATUS_REJECTED]);

                        Notification::make()
                            ->title('Event rejected')
                            ->warning()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->headerActions([
                BulkAction::make('approve')
                    ->label('Approve Selected')
                    ->icon('tabler-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(function (CommunityEvent $record) {
                            if ($record->status === CommunityEvent::STATUS_PENDING) {
                                $record->update([
                                    'status' => CommunityEvent::STATUS_APPROVED,
                                    'published_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('Events approved successfully')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('reject')
                    ->label('Reject Selected')
                    ->icon('tabler-x')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(function (CommunityEvent $record) {
                            if ($record->status === CommunityEvent::STATUS_PENDING) {
                                $record->update(['status' => CommunityEvent::STATUS_REJECTED]);
                            }
                        });

                        Notification::make()
                            ->title('Events rejected')
                            ->warning()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh for new submissions
    }
}
