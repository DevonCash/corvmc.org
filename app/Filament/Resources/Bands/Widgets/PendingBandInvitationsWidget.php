<?php

namespace App\Filament\Resources\Bands\Widgets;

use App\Actions\Bands\AcceptBandInvitation;
use App\Actions\Bands\DeclineBandInvitation;
use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingBandInvitationsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Pending Band Invitations')
            ->description('You have been invited to join the following bands.')
            ->columns([
                Tables\Columns\ImageColumn::make('band.avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->band->name) . '&color=7C3AED&background=F3E8FF&size=120'),
                Tables\Columns\TextColumn::make('band.name')
                    ->label('Band Name')
                    ->sortable()
                    ->description(fn($record) => $record->band->hometown)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn($value) => match ($value) {
                        'admin' => 'success',
                        'member' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('invited_at')
                    ->label('Invited')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->recordActions([
                AcceptBandInvitation::filamentAction(),
                DeclineBandInvitation::filamentAction(),
            ])
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        return BandMember::query()
            ->where('user_id', User::me()->id)
            ->where('status', 'invited')
            ->with(['band', 'user']);
    }

    public static function canView(): bool
    {
        return BandMember::query()
            ->where('user_id', User::me()->id)
            ->where('status', 'invited')
            ->exists();
    }
}
