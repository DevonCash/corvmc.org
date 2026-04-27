<?php

namespace App\Filament\Staff\Pages;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('tabler-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Hours')
                        ->modalDescription(fn (HourLog $record) => "Approve {$record->minutes} minutes submitted by {$record->user->name}?")
                        ->schema([
                            SpatieTagsInput::make('tags')
                                ->label('Tags (optional)')
                                ->placeholder('Add tags...'),
                        ])
                        ->action(function (HourLog $record, array $data) {
                            app(HourLogService::class)->approve(
                                $record,
                                auth()->user(),
                                $data['tags'] ?? [],
                            );

                            Notification::make()
                                ->title('Hours approved')
                                ->success()
                                ->send();
                        }),

                    Action::make('reject')
                        ->label('Reject')
                        ->icon('tabler-x')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject Hours')
                        ->modalDescription(fn (HourLog $record) => "Reject {$record->minutes} minutes submitted by {$record->user->name}?")
                        ->schema([
                            Textarea::make('notes')
                                ->label('Reason (optional)')
                                ->placeholder('Explain why these hours are being rejected...'),
                        ])
                        ->action(function (HourLog $record, array $data) {
                            app(HourLogService::class)->reject(
                                $record,
                                auth()->user(),
                                $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Hours rejected')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No pending submissions')
            ->emptyStateDescription('All self-reported hours have been reviewed.')
            ->emptyStateIcon('tabler-check');
    }
}
