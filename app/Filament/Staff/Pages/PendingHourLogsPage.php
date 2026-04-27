<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\ApproveHoursAction;
use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\RejectHoursAction;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PendingHourLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-clock-check';

    protected static ?string $navigationLabel = 'Pending Hours';

    protected static ?string $title = 'Pending Hour Submissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Volunteering';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.pending-hour-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('volunteer.hours.approve') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                HourLog::query()
                    ->whereState('status', Pending::class)
                    ->with(['user', 'position'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Volunteer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('position.title')
                    ->label('Position')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Start')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('ended_at')
                    ->label('End')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('minutes')
                    ->label('Minutes')
                    ->getStateUsing(fn (HourLog $record) => $record->minutes),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ApproveHoursAction::make(),
                    RejectHoursAction::make(),
                ]),
            ])
            ->emptyStateHeading('No pending submissions')
            ->emptyStateDescription('All self-reported hours have been reviewed.')
            ->emptyStateIcon('tabler-check');
    }
}
