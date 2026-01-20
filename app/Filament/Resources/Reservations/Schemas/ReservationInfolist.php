<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Filament\Actions\Action;
use App\Filament\Resources\Users\UserResource;
use App\Models\RehearsalReservation;
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
                            TextEntry::make('cost')
                                ->label('Total Cost')
                                ->state(fn(?Model $record) => ($record->cost?->getMinorAmount()->toFloat() ?? 0.0) / 100.0)
                                ->money('USD'),
                            TextEntry::make('payment_status')->badge()
                        ])->columnSpanFull(),
                        TextEntry::make('hours_used')->suffix(' hrs'),
                        TextEntry::make('free_hours_used')
                            ->label('Free Hours')
                            ->suffix(' hrs'),
                        TextEntry::make('price')->state('$15'),
                    ])
                    ->extraAttributes(['style' => 'padding: calc(var(--spacing) * 4);'])
                    ->visible(fn(?Model $record): bool => $record instanceof RehearsalReservation),
                Fieldset::make('Reserved By')->schema([
                    Flex::make([
                        ImageEntry::make('reservable.avatar')->circular()
                            ->hiddenLabel()
                            ->imageSize(48)
                            ->grow(false)
                            ->state(fn(?Model $record): ?string => $record?->reservable->getFilamentAvatarUrl()),
                        TextEntry::make('reservable.name')
                            ->listWithLineBreaks()
                            ->grow(true)
                            ->hiddenLabel()
                            ->state(fn(?Model $record): array => [
                                $record?->reservable->name,
                                $record?->reservable->email,
                            ]),
                        Action::make('view_reservable')
                            ->label('View Member')
                            ->iconButton()
                            ->outlined()
                            ->url(fn(?Model $record): ?string => UserResource::getUrl('edit', [
                                'record' => $record?->reservable->getKey(),
                            ]))
                            ->visible(fn(): bool => in_array(UserResource::class, Filament::getCurrentPanel()->getResources()))
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
