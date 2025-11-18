<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;


/**
 * @property \Filament\Schemas\Components\Form $form
 */
class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.simple-form-page';

    protected static string|\UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Profile';

    protected static ?string $slug = 'profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(User::me()->profile->toArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                MemberProfileForm::configure(
                    \Filament\Schemas\Components\Section::make('')
                        ->relationship('profile')
                ),
            ])
            ->model(User::me())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        User::me()->update($data);

        Notification::make()
            ->success()
            ->title('Profile updated')
            ->send();
    }
}
