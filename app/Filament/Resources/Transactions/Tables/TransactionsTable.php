<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('amount')
                    ->money('USD')
                    ->icon(fn($record) => match ($record->type) {
                        'recurring' => 'tabler-refresh',
                        'refund' => 'tabler-circle-x',
                        default => null,
                    })
                    ->iconPosition(IconPosition::After)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('email')
                    ->state(fn($record) => $record->response['donor_name'] ?? $record->user->name)
                    ->icon(fn($record) => $record->user ? 'tabler-user' : null)
                    ->iconPosition(IconPosition::After)
                    ->url(fn($record) => $record->user ? route('filament.member.resources.users.view', $record->user) : null)
                    ->color(fn($record) => $record->user ? 'info' : 'gray')
                    ->description(fn($record) => $record->email)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transaction ID copied')
                    ->limit(20),
                TextColumn::make('type')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'recurring',
                        'info' => 'donation',
                        'warning' => 'sponsorship',
                        'danger' => 'refund',
                    ]),

                TextColumn::make('response.campaign')
                    ->label('Campaign')
                    ->searchable()
                    ->placeholder('No campaign'),

                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->tooltip(fn($record) => $record->created_at->format('M j, Y g:i A')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'donation' => 'Donation',
                        'recurring' => 'Recurring',
                        'sponsorship' => 'Sponsorship',
                        'refund' => 'Refund',
                    ])
                    ->multiple(),

                SelectFilter::make('campaign')
                    ->label('Campaign')
                    ->options([
                        'general_support' => 'General Support',
                        'sustaining_membership' => 'Sustaining Membership',
                        'equipment_fund' => 'Equipment Fund',
                        'event_support' => 'Event Support',
                        'youth_program' => 'Youth Program',
                        'alumni_support' => 'Alumni Support',
                        'memorial_fund' => 'Memorial Fund',
                        'major_gift' => 'Major Gift',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereJsonContains('response->campaign', $data['values']);
                        }
                        return $query;
                    })
                    ->multiple(),

                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['values'])) {
                            return $query->whereJsonContains('response->status', $data['values']);
                        }
                        return $query;
                    })
                    ->multiple(),

                SelectFilter::make('member_status')
                    ->label('Member Status')
                    ->options([
                        'member' => 'Registered Members',
                        'non_member' => 'Community Supporters',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'member') {
                            return $query->whereHas('user');
                        } elseif ($data['value'] === 'non_member') {
                            return $query->whereDoesntHave('user');
                        }
                        return $query;
                    }),

                Filter::make('sustaining_level')
                    ->label('Sustaining Level ($10+)')
                    ->query(fn(Builder $query): Builder => $query->where('amount', '>=', 10))
                    ->toggle(),

                Filter::make('large_donations')
                    ->label('Large Donations ($100+)')
                    ->query(fn(Builder $query): Builder => $query->where('amount', '>=', 100))
                    ->toggle(),

                Filter::make('recent')
                    ->label('Recent (30 days)')
                    ->query(fn(Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(30)))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('view_zeffy_data')
                    ->label('View Zeffy Data')
                    ->icon('heroicon-o-eye')
                    ->color(Color::Gray)
                    ->modalHeading('Zeffy Webhook Data')
                    ->modalContent(fn($record) => view('filament.resources.transactions.actions.zeffy-data', ['transaction' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->deferLoading()
            ->striped();
    }
}
