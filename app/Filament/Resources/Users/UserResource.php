<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\BandProfilesRelationManager;
use App\Filament\Resources\Users\RelationManagers\ProductionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ReservationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-user-cog';
    protected static string|UnitEnum|null $navigationGroup = 'Admin';

    public static function getRecordTitle($record): string
    {
        return $record->name ?? 'Unknown User';
    }

    public static function canViewAny(): bool
    {
        return User::me()?->can('view users') ?? false;
    }

    public static function canCreate(): bool
    {
        return User::me()?->can('invite users') ?? null;
    }

    public static function canEdit($record): bool
    {
        return User::me()?->can('update users') ?? null;
    }

    public static function canDelete($record): bool
    {
        return User::me()?->can('delete users') ?? null;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BandProfilesRelationManager::class,
            ProductionsRelationManager::class,
            ReservationsRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/invite'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
