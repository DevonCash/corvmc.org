<?php

namespace App\Filament\Resources\Revisions\Tables;

use App\Models\Revision;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RevisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('revisionable_type')
                    ->label('Model Type')
                    ->formatStateUsing(fn (Revision $record) => $record->getModelTypeName())
                    ->searchable()
                    ->sortable(),

                TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->getStateUsing(fn (Revision $record) => $record->getChangesSummary())
                    ->wrap()
                    ->limit(50),

                TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => Revision::STATUS_PENDING,
                        'success' => Revision::STATUS_APPROVED,
                        'danger' => Revision::STATUS_REJECTED,
                    ])
                    ->sortable(),

                IconColumn::make('auto_approved')
                    ->label('Auto')
                    ->boolean()
                    ->trueIcon('tabler-circle-check')
                    ->falseIcon('tabler-user')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Revision $record) => $record->auto_approved ? 'Auto-approved by trust system' : 'Manual review')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn (Revision $record) => $record->created_at->format('M j, Y g:i A')),

                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn ($state) => $state?->format('M j, Y g:i A'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('revision_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'info' => Revision::TYPE_UPDATE,
                        'success' => Revision::TYPE_CREATE,
                        'danger' => Revision::TYPE_DELETE,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Revision::STATUS_PENDING => 'Pending',
                        Revision::STATUS_APPROVED => 'Approved',
                        Revision::STATUS_REJECTED => 'Rejected',
                    ])
                    ->default(Revision::STATUS_PENDING),

                SelectFilter::make('revisionable_type')
                    ->label('Model Type')
                    ->options([
                        'App\Models\MemberProfile' => 'Member Profile',
                        'App\Models\Band' => 'Band',
                        'App\Models\Event' => 'Event',
                    ]),

                SelectFilter::make('revision_type')
                    ->label('Revision Type')
                    ->options([
                        Revision::TYPE_UPDATE => 'Update',
                        Revision::TYPE_CREATE => 'Create',
                        Revision::TYPE_DELETE => 'Delete',
                    ]),

                SelectFilter::make('auto_approved')
                    ->label('Approval Method')
                    ->options([
                        '1' => 'Auto-approved',
                        '0' => 'Manual Review',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === '1') {
                            $query->where('auto_approved', true);
                        } elseif ($data['value'] === '0') {
                            $query->where('auto_approved', false);
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
