<?php

namespace App\Filament\Member\Resources\Reservations\Schemas;

use App\Filament\Shared\Actions\Action;
use App\Filament\Staff\Resources\Users\UserResource;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->dense()
            ->components([
                Fieldset::make('Reservation Time')
                    ->columns(1)
                    ->schema([
                        Flex::make([
                            TextEntry::make('reserved_at')
                                ->hiddenLabel()
                                ->listWithLineBreaks(true)
                                ->state(fn(?Model $record): array => [
                                    $record->reserved_at->format('F j, Y'),
                                    $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A')
                                ])->grow(true),
                            TextEntry::make('status')
                                ->label('Status')
                                ->hiddenLabel()
                                ->grow(false)
                                ->badge()
                        ])->extraAttributes(['style' => 'align-items: center;'])
                    ])
                    ->extraAttributes(['style' => 'padding: calc(var(--spacing) * 4);']),
                Fieldset::make('Payment')
                    ->columns(3)
                    ->schema([
                        Flex::make([
                            TextEntry::make('charge.status')
                                ->label('Total Cost')
                                ->badge()
                                ->beforeContent(fn(?Model $record): ?string => $record->charge?->net_amount->formatTo('en_US'))
                                ->placeholder('No charge'),
                            TextEntry::make('breakdown')
                                ->state(function (?Model $record): ?string {
                                    if (! $record instanceof RehearsalReservation) {
                                        return null;
                                    }

                                    return '(' . $record->hours_used . ' hrs - ' . $record->free_hours_used . ' free hrs) × $15';
                                })
                        ])->columnSpanFull(),
                    ])
                    ->extraAttributes(['style' => 'padding: calc(var(--spacing) * 4);'])
                    ->visible(fn(?Model $record): bool => $record instanceof RehearsalReservation),
                Fieldset::make('Reserved By')->schema([
                    Flex::make([
                        ImageEntry::make('reservable.avatar')->circular()
                            ->hiddenLabel()
                            ->imageSize(48)
                            ->grow(false)
                            ->state(function (?Model $record): ?string {
                                $reservable = $record?->reservable;
                                if ($reservable && method_exists($reservable, 'getFilamentAvatarUrl')) {
                                    return $reservable->getFilamentAvatarUrl();
                                }
                                return null;
                            }),
                        TextEntry::make('reservable.name')
                            ->listWithLineBreaks()
                            ->grow(true)
                            ->hiddenLabel()
                            ->state(function (?Model $record): array {
                                $reservable = $record?->reservable;
                                if ($reservable instanceof User) {
                                    $lines = [
                                        $reservable->name,
                                        $reservable->email,
                                    ];
                                    if ($reservable->phone) {
                                        $lines[] = $reservable->phone;
                                    }
                                    return $lines;
                                }
                                // For Events or other reservables
                                return [
                                    $record?->getDisplayTitle() ?? 'Unknown',
                                ];
                            }),
                        Action::make('view_reservable')
                            ->label('View Member')
                            ->iconButton()
                            ->outlined()
                            ->url(fn(?Model $record): ?string => UserResource::getUrl('edit', [
                                'record' => $record?->reservable->getKey(),
                            ]))
                            ->visible(
                                fn(?Model $record): bool =>
                                $record?->reservable instanceof User &&
                                    in_array(UserResource::class, Filament::getCurrentPanel()->getResources())
                            )
                            ->openUrlInNewTab(true)
                            ->icon('heroicon-o-arrow-top-right-on-square'),
                    ])
                        ->columnSpanFull()
                        ->dense()
                        ->extraAttributes(['style' => 'align-items: center;'])
                ])
                    ->visible(fn() => User::me()->can('manage users'))
                    ->extraAttributes(['style' => 'padding: calc(var(--spacing) * 1) calc(var(--spacing) * 3) calc(var(--spacing) * 3);']),
                TextEntry::make('notes')
                    ->label('Additional Notes')
                    ->columnSpanFull()
                    ->extraAttributes(['style' => 'padding: calc(var(--spacing) * 4);']),
            ]);
    }
}
