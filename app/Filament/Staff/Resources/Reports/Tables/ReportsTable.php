<?php

namespace App\Filament\Staff\Resources\Reports\Tables;

use CorvMC\Moderation\Models\Report;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reportable.name')
                    ->label('Content')
                    ->description(fn (Report $record) => class_basename($record->reportable_type).' #'.$record->reportable_id)
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }

                        return $state;
                    }),
                TextColumn::make('reason')
                    ->badge()
                    ->label('Reason')
                    ->formatStateUsing(fn ($state) => Report::REASONS[$state] ?? $state)
                    ->colors([
                        'danger' => ['inappropriate_content', 'harassment'],
                        'warning' => ['spam', 'misleading_info'],
                        'primary' => ['policy_violation'],
                        'gray' => 'other',
                    ]),

                TextColumn::make('reportedBy.name')
                    ->label('Reported By')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => Report::STATUSES[$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'danger' => 'upheld',
                        'success' => 'dismissed',
                        'primary' => 'escalated',
                    ]),

                TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('resolvedBy.name')
                    ->label('Resolved By')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Report::STATUSES)
                    ->default('pending'),

                SelectFilter::make('reportable_type')
                    ->label('Content Type')
                    ->options([
                        'CorvMC\Events\Models\Event' => 'Production',
                        'App\Models\MemberProfile' => 'Member Profile',
                        'App\Models\Band' => 'Band Profile',
                    ]),

                SelectFilter::make('reason')
                    ->options(Report::REASONS),

                Filter::make('unresolved')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'escalated']))
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(
                        fn (Report $record): string => route('filament.member.resources.reports.view', $record)
                    ),

            ])
            ->toolbarActions([
                \Filament\Actions\BulkAction::make('bulk_uphold')
                    ->label('Uphold Selected')
                    ->icon('tabler-circle-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->placeholder('Bulk resolution notes...')
                            ->rows(3),
                    ])
                    ->action(function ($records, array $data): void {
                        $count = \App\Actions\Reports\BulkResolveReports::run(
                            $records->pluck('id')->toArray(),
                            Auth::user(),
                            'upheld',
                            $data['resolution_notes'] ?? null
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Upheld {$count} reports")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\BulkAction::make('bulk_dismiss')
                    ->label('Dismiss Selected')
                    ->icon('tabler-circle-x')
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->placeholder('Bulk resolution notes...')
                            ->rows(3),
                    ])
                    ->action(function ($records, array $data): void {
                        $count = \App\Actions\Reports\BulkResolveReports::run(
                            $records->pluck('id')->toArray(),
                            Auth::user(),
                            'dismissed',
                            $data['resolution_notes'] ?? null
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Dismissed {$count} reports")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
