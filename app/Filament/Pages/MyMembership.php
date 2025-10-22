<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\Schemas\MembershipForm;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class MyMembership extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected string $view = 'filament.pages.simple-form-page';

    protected static string | \UnitEnum | null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Membership';

    protected static ?string $slug = 'membership';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                MembershipForm::configure(Grid::make(1)),
            ])
            ->model(User::me())
            ->statePath('data');
    }

}
