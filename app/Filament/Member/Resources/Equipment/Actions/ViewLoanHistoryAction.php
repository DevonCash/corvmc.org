<?php

namespace App\Filament\Member\Resources\Equipment\Actions;

use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewLoanHistoryAction
{
    public static function make(): Action
    {
        return Action::make('view_loan_history')
            ->label('Loan History')
            ->icon('tabler-history')
            ->color('gray')
            ->modalWidth('4xl')
            ->modalHeading(fn ($record) => "Loan History - {$record->name}")
            ->schema(function ($record): Schema {
                $loanHistory = $record->loans()->with('borrower')
                    ->orderByDesc('checked_out_at')
                    ->get();

                return Schema::make()
                    ->record($record)
                    ->schema([
                        Section::make('Equipment Overview')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->weight('bold'),
                                        TextEntry::make('type')
                                            ->formatStateUsing(
                                                fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                                            ),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'available' => 'success',
                                                'checked_out' => 'warning',
                                                'maintenance' => 'danger',
                                                default => 'gray',
                                            }),
                                    ]),
                            ]),

                        Section::make('Loan History')
                            ->schema([
                                RepeatableEntry::make('loan_history')
                                    ->label('')
                                    ->state($loanHistory->toArray())
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('borrower.name')
                                                    ->label('Borrower')
                                                    ->weight('bold'),
                                                TextEntry::make('checked_out_at')
                                                    ->label('Checked Out')
                                                    ->dateTime('M j, Y g:i A'),
                                                TextEntry::make('returned_at')
                                                    ->label('Returned')
                                                    ->dateTime('M j, Y g:i A')
                                                    ->placeholder('Still out'),
                                                TextEntry::make('status')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'active' => 'warning',
                                                        'returned' => 'success',
                                                        'overdue' => 'danger',
                                                        default => 'gray',
                                                    }),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('condition_out')
                                                    ->label('Condition Out')
                                                    ->formatStateUsing(
                                                        fn (?string $state): string => $state ? ucfirst($state) : 'Not recorded'
                                                    ),
                                                TextEntry::make('condition_in')
                                                    ->label('Condition In')
                                                    ->formatStateUsing(
                                                        fn (?string $state): string => $state ? ucfirst($state) : 'Not recorded'
                                                    ),
                                            ]),

                                        TextEntry::make('notes')
                                            ->columnSpanFull()
                                            ->placeholder('No notes')
                                            ->visible(fn ($state) => ! empty($state['notes'])),

                                        TextEntry::make('damage_notes')
                                            ->label('Damage Notes')
                                            ->columnSpanFull()
                                            ->placeholder('No damage notes')
                                            ->visible(fn ($state) => ! empty($state['damage_notes']))
                                            ->color('danger'),
                                    ])
                                    ->contained(false),
                            ])
                            ->visible($loanHistory->isNotEmpty()),

                        Section::make('No Loan History')
                            ->schema([
                                TextEntry::make('no_history')
                                    ->hiddenLabel()
                                    ->default('This equipment has never been loaned out.')
                                    ->color('gray'),
                            ])
                            ->visible($loanHistory->isEmpty()),
                    ]);
            })
            ->modalIcon('tabler-history');
    }
}
