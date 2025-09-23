<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\BandsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ProductionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ReservationsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-user-cog';
    protected static string|UnitEnum|null $navigationGroup = 'Admin';


    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->can('view users') ?? false;
    }

    public static function getRecordTitle($record): string
    {
        return $record->name ?? 'Unknown User';
    }

    public static function canViewAny(): bool
    {
        // Allow access so users can edit their own records, but restrict list access in the table
        return true;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('invite users') ?? null;
    }

    public static function canView($record): bool
    {
        return Auth::user()->is($record) || Auth::user()?->can('view users') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->is($record) || Auth::user()?->can('update users') ?? null;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('delete users') ?? null;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $table = UsersTable::configure($table);

        // Restrict table to current user if they don't have view users permission
        if (!Auth::user()?->can('view users')) {
            $table->modifyQueryUsing(fn($query) => $query->where('id', Auth::id()));
        }

        return $table;
    }

    public static function getRelations(): array
    {
        return [
            BandsRelationManager::class,
            ProductionsRelationManager::class,
            ReservationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/invite'),
            'edit' => EditUser::route('/{record}'),
        ];
    }
}
