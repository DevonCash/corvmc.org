<?php

namespace App\Filament\Resources\MemberProfiles;

use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Resources\MemberProfiles\Pages\ViewMemberProfile;
use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\MemberProfiles\Tables\MemberProfilesTable;
use App\Models\MemberProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MemberProfileResource extends Resource
{
    protected static ?string $slug = 'directory';

    protected static ?string $model = MemberProfile::class;

    protected static ?string $label = 'Member Profile';

    protected static ?string $pluralLabel = 'Member Directory';

    protected static ?string $navigationLabel = 'Member Directory';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?string $recordTitleAttribute = 'user.name';

    protected static ?string $recordSubtitleAttribute = 'hometown';

    public static function form(Schema $schema): Schema
    {
        return MemberProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberProfiles::route('/'),
            'view' => ViewMemberProfile::route('/{record}'),
            'edit' => EditMemberProfile::route('/{record}/edit'),
        ];
    }

    // Global scope automatically handles visibility filtering
}
