<?php

namespace App\Filament\Resources\Reservations\Widgets;

use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecurringSeriesTableWidget extends BaseWidget
{
    protected string $view = 'filament.resources.reservations.widgets.recurring-series-table-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public bool $isSustainingMember = false;

    public function mount(): void
    {
        $this->isSustainingMember = User::me()?->isSustainingMember() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RecurringSeries::query()
                    ->where('recurable_type', Reservation::class)
                    ->where('user_id', Auth::id())
                    ->with(['user', 'instances'])
            )
            ->heading('My Recurring Reservations')
            ->emptyStateHeading('No recurring reservations')
            ->emptyStateDescription('Create a recurring reservation to automatically book the practice space on a regular schedule.')
            ->emptyStateIcon('tabler-clock-repeat')
            ->columns([

                TextColumn::make('recurrence_rule')
                    ->label('Pattern')
                    ->formatStateUsing(fn($state) => \App\Actions\RecurringReservations\FormatRRuleForHumans::run($state))
                    ->wrap(),

                TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn($record) => $record->start_time->format('g:i A') . ' - ' . $record->end_time->format('g:i A')),

                TextColumn::make('series_start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('series_end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Ongoing'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'cancelled',
                        'gray' => 'completed',
                    ]),

                TextColumn::make('instances_count')
                    ->label('Instances')
                    ->counts('instances')
                    ->suffix(' total'),

                TextColumn::make('active_instances_count')
                    ->label('Active')
                    ->counts('activeInstances')
                    ->suffix(' active'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
            ])
            ->recordActions([
                Action::make('view_instances')
                    ->label('View Instances')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.member.resources.reservations.index', [
                        'tableFilters[recurring_series_id][value]' => $record->id,
                    ])),

                Action::make('cancel')
                    ->label('Cancel Series')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'active')
                    ->authorize('delete')
                    ->action(fn($record) => \App\Actions\RecurringReservations\CancelRecurringSeries::run($record)),
            ])
            ->defaultSort('series_start_date', 'desc')
            ->paginated([10, 25, 50]);
    }
}
