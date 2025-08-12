<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->copyable()
                                    ->copyMessage('Transaction ID copied'),

                                TextEntry::make('email')
                                    ->copyable()
                                    ->copyMessage('Email copied'),

                                TextEntry::make('amount')
                                    ->money('USD')
                                    ->size('lg')
                                    ->weight('bold'),

                                TextEntry::make('currency')
                                    ->badge(),

                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'recurring' => 'success',
                                        'donation' => 'info',
                                        'sponsorship' => 'warning',
                                        'refund' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('created_at')
                                    ->label('Received')
                                    ->dateTime()
                                    ->since(),
                            ]),
                    ]),

                Section::make('Member Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Member Name')
                                    ->placeholder('Non-member'),

                                TextEntry::make('user.email')
                                    ->label('Member Email')
                                    ->placeholder('Not a registered member'),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->user),

                Section::make('Donation Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('response.donor_name')
                                    ->label('Donor Name')
                                    ->placeholder('Anonymous'),

                                TextEntry::make('response.campaign')
                                    ->label('Campaign')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'general_support' => 'primary',
                                        'sustaining_membership' => 'success',
                                        'equipment_fund' => 'warning',
                                        'event_support' => 'info',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state ? ucfirst(str_replace('_', ' ', $state)) : 'No campaign'
                                    ),

                                TextEntry::make('response.status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (?string $state): string => match (strtolower($state ?? '')) {
                                        'completed', 'success', 'paid' => 'success',
                                        'pending', 'processing' => 'warning',
                                        'failed', 'cancelled', 'declined' => 'danger',
                                        default => 'gray',
                                    })
                                    ->default('completed'),

                                TextEntry::make('response.payment_method')
                                    ->label('Payment Method')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'credit_card' => 'primary',
                                        'bank_transfer' => 'success',
                                        'paypal' => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state ? ucfirst(str_replace('_', ' ', $state)) : 'Unknown'
                                    ),

                                TextEntry::make('response.is_recurring')
                                    ->label('Recurring')
                                    ->badge()
                                    ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Yes' : 'No'),

                                TextEntry::make('response.timestamp')
                                    ->label('Zeffy Timestamp')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('response.note')
                            ->label('Note')
                            ->columnSpanFull(),

                        TextEntry::make('response.memorial_note')
                            ->label('Memorial Note')
                            ->columnSpanFull(),

                        TextEntry::make('response.honoree_name')
                            ->label('In Memory Of'),

                        TextEntry::make('response.zeffy_form_id')
                            ->label('Zeffy Form ID')
                            ->copyable()
                            ->fontFamily('mono'),

                        TextEntry::make('response.subscription_id')
                            ->label('Subscription ID')
                            ->copyable()
                            ->fontFamily('mono'),

                        TextEntry::make('response.designated_fund')
                            ->label('Designated Fund')
                            ->badge(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Additional Questions')
                    ->schema([
                        TextEntry::make('response.additional_questions')
                            ->label('Campaign-Specific Questions')
                            ->formatStateUsing(function ($state) {
                                if (empty($state) || !is_array($state)) {
                                    return 'No additional questions';
                                }
                                
                                $output = '';
                                foreach ($state as $question => $answer) {
                                    $output .= "**{$question}:** {$answer}\n\n";
                                }
                                return trim($output);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->response['additional_questions'] ?? []))
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
