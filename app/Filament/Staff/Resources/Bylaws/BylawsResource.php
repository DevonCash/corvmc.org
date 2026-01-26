<?php

namespace App\Filament\Staff\Resources\Bylaws;

use App\Filament\Staff\Resources\Bylaws\Pages\ManageBylaws;
use App\Models\User;
use Filament\Resources\Resource;

class BylawsResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-license';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Bylaws';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {

        return User::me()->hasRole('admin');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBylaws::route('/'),
        ];
    }
}
