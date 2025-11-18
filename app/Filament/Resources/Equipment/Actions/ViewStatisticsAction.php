<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewStatisticsAction
{
    public static function make(): Action
    {
        return Action::make('view_statistics')
            ->label('Equipment Statistics')
            ->icon('tabler-chart-infographic')
            ->color('info')
            ->modalWidth(width: '3xl')
            ->modalHeading('Equipment Library Statistics')
            ->schema(function (): Schema {
                $stats = \App\Actions\Equipment\GetStatistics::run();
                $valueByType = \App\Actions\Equipment\GetValueByAcquisitionType::run();
                $popular = Equipment::popular()->limit(5)->get();

                return Schema::make()
                    ->schema([
                        Section::make('Equipment Overview')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('total_equipment')
                                            ->label('Total Equipment')
                                            ->default($stats['total_equipment'])
                                            ->badge()
                                            ->color('primary'),
                                        TextEntry::make('available_equipment')
                                            ->label('Available')
                                            ->default($stats['available_equipment'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('checked_out_equipment')
                                            ->label('Checked Out')
                                            ->default($stats['checked_out_equipment'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('maintenance_equipment')
                                            ->label('In Maintenance')
                                            ->default($stats['maintenance_equipment'])
                                            ->badge()
                                            ->color('danger'),
                                    ]),
                            ]),

                        Section::make('Loan Activity')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('active_loans')
                                            ->label('Active Loans')
                                            ->default($stats['active_loans'])
                                            ->badge()
                                            ->color('info'),
                                        TextEntry::make('overdue_loans')
                                            ->label('Overdue Loans')
                                            ->default($stats['overdue_loans'])
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('loan_rate')
                                            ->label('Utilization Rate')
                                            ->default(function () use ($stats) {
                                                $total = $stats['total_equipment'];
                                                $checkedOut = $stats['checked_out_equipment'];

                                                return $total > 0 ? round(($checkedOut / $total) * 100, 1).'%' : '0%';
                                            })
                                            ->badge()
                                            ->color('secondary'),
                                    ]),
                            ]),

                        Section::make('Acquisition Sources')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('donated_equipment')
                                            ->label('Donated Items')
                                            ->default($stats['donated_equipment'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('loaned_to_cmc')
                                            ->label('On Loan to CMC')
                                            ->default($stats['loaned_to_cmc'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('purchased_equipment')
                                            ->label('Purchased Items')
                                            ->default(function () use ($stats) {
                                                return $stats['total_equipment'] - $stats['donated_equipment'] - $stats['loaned_to_cmc'];
                                            })
                                            ->badge()
                                            ->color('primary'),
                                    ]),
                            ]),

                        Section::make('Equipment Value by Source')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('donated_value')
                                            ->label('Donated Value')
                                            ->default('$'.number_format($valueByType['donated'] ?? 0, 2))
                                            ->color('success'),
                                        TextEntry::make('loaned_value')
                                            ->label('Loaned Value')
                                            ->default('$'.number_format($valueByType['loaned_to_us'] ?? 0, 2))
                                            ->color('warning'),
                                        TextEntry::make('purchased_value')
                                            ->label('Purchased Value')
                                            ->default('$'.number_format($valueByType['purchased'] ?? 0, 2))
                                            ->color('primary'),
                                    ]),
                            ])
                            ->visible(! empty(array_filter($valueByType))),

                        Section::make('Most Popular Equipment')
                            ->schema([
                                TextEntry::make('popular_equipment')
                                    ->hiddenLabel()
                                    ->default(function () use ($popular) {
                                        if ($popular->isEmpty()) {
                                            return 'No loan history available';
                                        }

                                        return $popular
                                            ->map(fn (\App\Models\Equipment $item) => "{$item->name} ({$item->loans_count} loans)")
                                            ->join("\n");
                                    })
                                    ->extraAttributes(['class' => 'whitespace-pre-line']),
                            ])
                            ->visible($popular->isNotEmpty()),
                    ]);
            })
            ->modalIcon('tabler-chart-infographic');
    }
}
