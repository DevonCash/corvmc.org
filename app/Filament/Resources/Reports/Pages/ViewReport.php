<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\Actions\DismissReportAction;
use App\Filament\Resources\Reports\Actions\EscalateReportAction;
use App\Filament\Resources\Reports\Actions\UpholdReportAction;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;



    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Report Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Report ID'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn($state) => Report::STATUSES[$state] ?? $state)
                                    ->colors([
                                        'warning' => 'pending',
                                        'danger' => 'upheld',
                                        'success' => 'dismissed',
                                        'primary' => 'escalated',
                                    ]),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('reason')
                                    ->label('Reason')
                                    ->formatStateUsing(fn($state) => Report::REASONS[$state] ?? $state)
                                    ->badge(),

                                TextEntry::make('created_at')
                                    ->label('Reported At')
                                    ->dateTime(),
                            ]),

                        TextEntry::make('custom_reason')
                            ->label('Additional Details')
                            ->placeholder('No additional details provided')
                            ->visible(fn(Report $record) => !empty($record->custom_reason)),
                    ]),

                Section::make('Reported Content')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('reportable_type')
                                    ->label('Content Type')
                                    ->formatStateUsing(function ($state) {
                                        return match ($state) {
                                            'App\Models\Production' => 'Production',
                                            'App\Models\MemberProfile' => 'Member Profile',
                                            'App\Models\Band' => 'Band Profile',
                                            default => $state
                                        };
                                    })
                                    ->badge(),

                                TextEntry::make('reportable.title')
                                    ->label('Content Title')
                                    ->placeholder('N/A'),
                            ]),

                        ViewEntry::make('content_preview')
                            ->label('Content Preview')
                            ->view('filament.resources.reports.content-preview'),
                    ]),

                Section::make('Reporter Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('reportedBy.name')
                                    ->label('Reported By'),

                                TextEntry::make('reportedBy.email')
                                    ->label('Reporter Email')
                                    ->visible(fn() => Auth::user()->hasRole('admin')),
                            ]),
                    ]),

                Section::make('Resolution')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('resolvedBy.name')
                                    ->label('Resolved By')
                                    ->placeholder('Not yet resolved'),

                                TextEntry::make('resolved_at')
                                    ->label('Resolved At')
                                    ->dateTime()
                                    ->placeholder('Not yet resolved'),
                            ]),

                        TextEntry::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->placeholder('No resolution notes')
                            ->visible(fn(Report $record) => !empty($record->resolution_notes)),
                    ])
                    ->visible(fn(Report $record) => $record->isResolved()),

                Section::make('Activity Log')
                    ->schema([
                        ViewEntry::make('activity_log')
                            ->view('filament.resources.reports.activity-log'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            UpholdReportAction::make(),
            DismissReportAction::make(),
            EscalateReportAction::make(),
        ];
    }

    public function getContentUrl($reportable): string
    {
        return match (get_class($reportable)) {
            'App\Models\Production' => route('filament.member.resources.productions.view', $reportable),
            'App\Models\MemberProfile' => route('filament.member.resources.directory.view', $reportable),
            'App\Models\Band' => route('filament.member.resources.bands.view', $reportable),
            default => '#',
        };
    }
}
