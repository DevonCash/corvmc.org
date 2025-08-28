<?php

namespace App\Filament\Widgets;

use App\Models\Band;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions;

class MyBandsWidget extends BaseWidget
{
    protected static ?string $heading = 'My Bands';

    protected int | string | array $columnSpan = 1;

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
                    ->defaultImageUrl(fn() => 'https://ui-avatars.com/api/?name=Band&color=7c3aed&background=ede9fe'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Band Name')
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(function (Band $record): string {
                        $user = User::me();

                        // Owner
                        if ($record->owner_id === $user->id) {
                            return 'owner';
                        }

                        // Check membership
                        $membership = $record->members()->where('users.id', $user->id)->first();
                        if (!$membership) {
                            return 'none';
                        }

                        // Check if invited
                        if ($membership->pivot->status === 'invited') {
                            return 'invited';
                        }

                        return $membership->pivot->role ?? 'member';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'owner' => 'primary',
                        'admin' => 'warning',
                        'member' => 'success',
                        'invited' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->icon(fn(string $state): string => match ($state) {
                        'public' => 'tabler-world',
                        'members' => 'tabler-users',
                        'private' => 'tabler-lock',
                        default => 'tabler-eye-off',
                    })
                    ->color(fn(string $state): string => match ($state) {
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
                        fn($state, $record) =>
                        $state . ($record->pending_invitations > 0 ? " (+{$record->pending_invitations})" : '')
                    ),
            ])
            ->recordActions([
                Actions\Action::make('view')
                    ->label('View')
                    ->icon('tabler-eye')
                    ->url(fn(Band $record) => route('filament.member.resources.bands.view', $record))
                    ->openUrlInNewTab(),

                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('tabler-edit')
                    ->url(fn(Band $record) => route('filament.member.resources.bands.edit', $record))
                    ->visible(fn(Band $record) => $this->canEditBand($record))
                    ->openUrlInNewTab(),

                Actions\Action::make('manage_members')
                    ->label('Members')
                    ->icon('tabler-users-plus')
                    ->url(fn(Band $record) => route('filament.member.resources.bands.edit', $record) . '#members')
                    ->visible(fn(Band $record) => $this->canManageBand($record) && $record->pending_invitations > 0)
                    ->openUrlInNewTab(),

                Actions\Action::make('primary_link')
                    ->label('Visit')
                    ->icon(fn(Band $record) => $this->getPrimaryLinkIcon($record))
                    ->url(fn(Band $record) => $this->getPrimaryLinkUrl($record))
                    ->visible(fn(Band $record) => $this->getPrimaryLinkUrl($record) !== null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Actions\Action::make('view_all')
                    ->label('See All')
                    ->icon('tabler-settings')
                    ->url(route('filament.member.resources.bands.index')),
            ])
            ->emptyStateHeading('No bands yet')
            ->emptyStateDescription('You haven\'t joined or created any bands.')
            ->emptyStateIcon('tabler-users-off')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label('Create Your First Band')
                    ->icon('tabler-plus')
                    ->url(route('filament.member.resources.bands.create'))
                    ->visible(fn() => User::me()?->can('create bands') ?? false),
            ])
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        $user = User::me();

        if (!$user) {
            return Band::query()->whereRaw('1=0'); // Empty query
        }

        return Band::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                      ->orWhereHas('members', function ($q) use ($user) {
                          $q->where('users.id', $user->id);
                      });
            })
            ->with(['owner', 'activeMembers', 'pendingInvitations'])
            ->withCount(['activeMembers as member_count', 'pendingInvitations'])
            ->orderBy('name');
    }


    protected function canEditBand(Band $band): bool
    {
        $user = User::me();

        if (!$user) {
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

        // Band admins can edit
        $membership = $band->members()->where('users.id', $user->id)->first();
        return $membership?->pivot->role === 'admin';
    }

    protected function canManageBand(Band $band): bool
    {
        $user = User::me();

        if (!$user) {
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

        // Band admins can manage
        $membership = $band->members()->where('users.id', $user->id)->first();
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
        return auth()->check(); // Only authenticated users can see their bands
    }
}
