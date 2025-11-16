<?php

namespace App\Filament\Resources\Bylaws;

use App\Filament\Resources\Bylaws\Pages\ManageBylaws;
use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;

class BylawsResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = 'tabler-license';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Bylaws';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {

        return Auth::user()->hasRole('admin');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBylaws::route('/'),
        ];
    }
}
