<?php

namespace App\Filament\Resources\Revisions\Tables;

use App\Filament\Resources\Revisions\Actions\ReviewRevisionAction;
use CorvMC\Moderation\Models\Revision;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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

                TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->description(fn(Revision $record) => $record->submittedBy?->email)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('revisionable_type')
                    ->label('Item')
                    ->description(fn(Revision $record) => $record->getModelTypeName() . ' #' . $record->revisionable_id)
                    ->formatStateUsing(fn(Revision $record) => $record->getRevisionableTitle())
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Approval')
                    ->iconColor(fn($record) => match ($record->status) {
                        Revision::STATUS_PENDING => 'warning',
                        Revision::STATUS_APPROVED => 'success',
                        Revision::STATUS_REJECTED => 'danger',
                    })
                    ->icon(fn(Revision $record) => $record->auto_approved ?
                        match ($record->status) {
                            Revision::STATUS_APPROVED => 'tabler-settings-check',
                            Revision::STATUS_REJECTED => 'tabler-settings-cancel',
                            default => null,
                        } :
                        match ($record->status) {
                            Revision::STATUS_APPROVED => 'tabler-circle-check',
                            Revision::STATUS_REJECTED => 'tabler-cancel',
                            default => 'tabler-dots-circle-horizontal',
                        })
                    ->tooltip(fn(Revision $record) => $record->auto_approved ? 'Auto-approved by trust system' : null)
                    ->sortable()
                    ->description(fn($record) => $record->reviewed_at?->format('M j, Y g:i A'))
                    ->formatStateUsing(fn(Revision $record) => $record->auto_approved ? 'Automatic' : ($record->reviewedBy?->name ?? 'Pending'))
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('reviewedBy', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn(Revision $record) => $record->created_at->format('M j, Y g:i A')),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn($state) => $state?->format('M j, Y g:i A'))
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
            ->recordAction('reviewRevision')
            ->recordActions([
                ReviewRevisionAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
