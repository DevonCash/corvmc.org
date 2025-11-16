<?php

namespace App\Filament\Resources\Equipment\Tables;

use App\Filament\Resources\Equipment\Actions\CheckoutToMemberAction;
use App\Models\Equipment;
use App\Settings\EquipmentSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EquipmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with(['children'])
                    ->whereNull('parent_equipment_id') // Only show root equipment (kits and standalone items)
                    ->where('ownership_status', 'cmc_owned')
                    ->whereIn('status', ['available', 'checked_out', 'maintenance'])
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(
                        fn ($record): string => collect([$record->brand, $record->model])->filter()->join(' ')
                    ),

                TextColumn::make('type')
                    ->formatStateUsing(
                        fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                    )
                    ->colors([
                        'primary' => ['guitar', 'bass'],
                        'success' => ['amplifier', 'pa_system'],
                        'warning' => ['drums', 'cymbals', 'drum_kit'],
                        'info' => ['microphone', 'recording'],
                        'secondary' => ['keyboard', 'hardware'],
                        'gray' => 'specialty',
                    ]),

                TextColumn::make('status')
                    ->badge()
                    ->visible(fn () => app(EquipmentSettings::class)->enable_rental_features)
                    ->formatStateUsing(function (string $state, $record): string {
                        if ($record->is_kit && $record->children->isNotEmpty()) {
                            $available = $record->children->where('status', 'available')->count();
                            $total = $record->children->count();
                            if ($available === $total) {
                                return 'All Available';
                            } elseif ($available === 0) {
                                return 'All In Use';
                            } else {
                                return "{$available}/{$total} Available";
                            }
                        }

                        return match ($state) {
                            'available' => 'Available',
                            'checked_out' => 'In Use',
                            'maintenance' => 'Maintenance',
                            default => ucfirst(str_replace('_', ' ', $state)),
                        };
                    })
                    ->colors([
                        'success' => function ($record) {
                            if ($record->is_kit && $record->children->isNotEmpty()) {
                                return $record->children->every(fn ($child) => $child->status === 'available');
                            }

                            return $record->status === 'available';
                        },
                        'warning' => function ($record) {
                            if ($record->is_kit && $record->children->isNotEmpty()) {
                                $available = $record->children->where('status', 'available')->count();

                                return $available > 0 && $available < $record->children->count();
                            }

                            return $record->status === 'checked_out';
                        },
                        'danger' => function ($record) {
                            if ($record->is_kit && $record->children->isNotEmpty()) {
                                return $record->children->every(fn ($child) => $child->status !== 'available');
                            }

                            return $record->status === 'maintenance';
                        },
                    ]),

                TextColumn::make('condition')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => ucfirst(str_replace('_', ' ', $state))
                    )
                    ->colors([
                        'success' => 'excellent',
                        'primary' => 'good',
                        'warning' => 'fair',
                        'danger' => ['poor', 'needs_repair'],
                    ])
                    ->toggleable(),

                TextColumn::make('location')
                    ->toggleable()
                    ->placeholder('Not specified')
                    ->visible(fn ($record) => Auth::user()->can('manage equipment')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'guitar' => 'Guitar',
                        'bass' => 'Bass',
                        'drum_kit' => 'Drum Kit',
                        'drums' => 'Drums',
                        'cymbals' => 'Cymbals',
                        'amplifier' => 'Amplifier',
                        'microphone' => 'Microphone',
                        'recording' => 'Recording Equipment',
                        'pa_system' => 'PA System',
                        'keyboard' => 'Keyboard',
                        'hardware' => 'Hardware/Stands',
                        'specialty' => 'Specialty',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'checked_out' => 'Checked Out',
                        'maintenance' => 'Under Maintenance',
                    ]),

                SelectFilter::make('condition')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                        'needs_repair' => 'Needs Repair',
                    ]),

                SelectFilter::make('is_kit')
                    ->label('Equipment Type')
                    ->options([
                        '1' => 'Kits Only',
                        '0' => 'Individual Items Only',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['value'])) {
                            return $query;
                        }

                        return $query->where('is_kit', (bool) $data['value']);
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                CheckoutToMemberAction::make()
                    ->visible(fn ($record) => app(EquipmentSettings::class)->enable_rental_features && $record->isAvailable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
