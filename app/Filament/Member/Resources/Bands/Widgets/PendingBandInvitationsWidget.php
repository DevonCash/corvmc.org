<?php

namespace App\Filament\Member\Resources\Bands\Widgets;

use App\Filament\Actions\Bands\AcceptBandInvitationAction;
use App\Filament\Actions\Bands\DeclineBandInvitationAction;
use App\Models\User;
use CorvMC\Support\Models\Invitation;
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
                Tables\Columns\ImageColumn::make('invitable.avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->invitable->name).'&color=7C3AED&background=F3E8FF&size=120'),
                Tables\Columns\TextColumn::make('invitable.name')
                    ->label('Band Name')
                    ->sortable()
                    ->description(fn ($record) => $record->invitable->hometown)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('data.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn ($value) => match ($value) {
                        'admin' => 'success',
                        'member' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('data.position')
                    ->label('Position')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Invited')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->recordActions([
                AcceptBandInvitationAction::make(),
                DeclineBandInvitationAction::make(),
            ])
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        $user = User::me();

        if (! $user) {
            return Invitation::query()->whereRaw('1=0');
        }

        return Invitation::query()
            ->where('user_id', $user->id)
            ->where('invitable_type', 'band')
            ->where('status', 'pending')
            ->with('invitable');
    }

    public static function canView(): bool
    {
        $user = User::me();

        if (! $user) {
            return false;
        }

        return Invitation::query()
            ->where('user_id', $user->id)
            ->where('invitable_type', 'band')
            ->where('status', 'pending')
            ->exists();
    }
}
