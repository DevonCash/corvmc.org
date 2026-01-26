<?php

namespace App\Filament\Member\Widgets;

use CorvMC\Membership\Actions\Bands\CreateBand;
use CorvMC\Bands\Models\Band;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyBandsWidget extends BaseWidget
{
    protected static ?string $heading = 'My Bands';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->imageSize(40)
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Band&color=7c3aed&background=ede9fe'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Band Name')
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(function (Band $record): string {
                        $user = Auth::user();

                        // Owner
                        if ($record->owner_id === $user->id) {
                            return 'owner';
                        }

                        // Check membership (already eager loaded)
                        $membership = $record->members->first();
                        if (! $membership) {
                            return 'none';
                        }

                        // Check if invited
                        /** @phpstan-ignore property.notFound */
                        if ($membership->pivot->status === 'invited') {
                            return 'invited';
                        }

                        /** @phpstan-ignore property.notFound */
                        return $membership->pivot->role ?? 'member';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'primary',
                        'admin' => 'warning',
                        'member' => 'success',
                        'invited' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'public' => 'tabler-world',
                        'members' => 'tabler-users',
                        'private' => 'tabler-lock',
                        default => 'tabler-eye-off',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'members' => 'info',
                        'private' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('member_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(
                        fn ($state, Band $record) => $state.($record->pending_invitations > 0 ? " (+{$record->pending_invitations})" : '')
                    ),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('tabler-eye')
                    ->url(fn (Band $record) => route('filament.member.resources.bands.view', $record))
                    ->openUrlInNewTab(),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('tabler-edit')
                    ->url(fn (Band $record) => route('filament.member.resources.bands.edit', $record))
                    ->visible(fn (Band $record) => $this->canEditBand($record))
                    ->openUrlInNewTab(),

                Action::make('manage_members')
                    ->label('Members')
                    ->icon('tabler-users-plus')
                    ->url(fn (Band $record) => route('filament.member.resources.bands.edit', $record).'#members')
                    ->visible(fn (Band $record) => $this->canManageBand($record) && $record->pending_invitations > 0)
                    ->openUrlInNewTab(),

                Action::make('primary_link')
                    ->label('Visit')
                    ->icon(fn (Band $record) => $this->getPrimaryLinkIcon($record))
                    ->url(fn (Band $record) => $this->getPrimaryLinkUrl($record))
                    ->visible(fn (Band $record) => $this->getPrimaryLinkUrl($record) !== null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('view_all')
                    ->label('See All')
                    ->icon('tabler-settings')
                    ->url(route('filament.member.resources.bands.index')),
            ])
            ->emptyStateHeading('No bands yet')
            ->emptyStateDescription('You haven\'t joined or created any bands.')
            ->emptyStateIcon('tabler-users-off')
            ->emptyStateActions([
                CreateBand::filamentAction()
                    ->label('Create Your First Band'),
            ])
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return Band::query()->whereRaw('1=0'); // Empty query
        }

        return Band::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    });
            })
            ->with([
                'owner',
                'activeMembers',
                'pendingInvitations',
                'members' => function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                },
            ])
            ->withCount(['activeMembers as member_count', 'pendingInvitations'])
            ->orderBy('name');
    }

    protected function canEditBand(Band $band): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Owner can always edit
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Check permissions
        if ($user->can('update bands')) {
            return true;
        }

        // Band admins can edit (already eager loaded)
        $membership = $band->members->first();

        /** @phpstan-ignore property.notFound */
        return $membership?->pivot->role === 'admin';
    }

    protected function canManageBand(Band $band): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Owner can manage
        if ($band->owner_id === $user->id) {
            return true;
        }

        // Check permissions
        if ($user->can('manage band members')) {
            return true;
        }

        // Band admins can manage (already eager loaded)
        $membership = $band->members->first();

        /** @phpstan-ignore property.notFound */
        return $membership?->pivot->role === 'admin';
    }

    protected function getPrimaryLinkUrl(Band $band): ?string
    {
        $primaryLink = $band->primaryLink();

        return $primaryLink['url'] ?? null;
    }

    protected function getPrimaryLinkIcon(Band $band): string
    {
        $primaryLink = $band->primaryLink();

        return str_replace('tabler:', 'tabler-', $primaryLink['icon'] ?? 'tabler-external-link');
    }

    public static function canView(): bool
    {
        return Auth::id() !== null; // Only authenticated users can see their bands
    }
}
