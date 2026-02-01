<?php

namespace App\Filament\Staff\Resources\KioskDevices;

use App\Filament\Staff\Resources\KioskDevices\Pages\CreateKioskDevice;
use App\Filament\Staff\Resources\KioskDevices\Pages\EditKioskDevice;
use App\Filament\Staff\Resources\KioskDevices\Pages\ListKioskDevices;
use App\Filament\Staff\Resources\KioskDevices\Schemas\KioskDeviceForm;
use App\Filament\Staff\Resources\KioskDevices\Tables\KioskDevicesTable;
use CorvMC\Kiosk\Models\KioskDevice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class KioskDeviceResource extends Resource
{
    protected static ?string $model = KioskDevice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-device-tablet';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Kiosk Devices';

    protected static ?string $pluralModelLabel = 'Kiosk Devices';

    protected static ?string $modelLabel = 'Kiosk Device';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return KioskDeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KioskDevicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKioskDevices::route('/'),
            'create' => CreateKioskDevice::route('/create'),
            'edit' => EditKioskDevice::route('/{record}/edit'),
        ];
    }
}
