<?php

namespace App\Filament\Band\Pages;

use App\Actions\Bands\UpdateBand;
use App\Filament\Resources\Bands\Schemas\BandForm;
use App\Models\Band;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class EditBandProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $navigationLabel = 'Band Profile';

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.simple-form-page';

    public ?array $data = [];

    public function mount(): void
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        $this->form->fill($band->toArray());
    }

    public function getTitle(): string
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        return 'Edit ' . $band->name;
    }

    public function form(Schema $form): Schema
    {
        return BandForm::configure($form)->statePath('data');
    }

    public function save(): void
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        $data = $this->form->getState();
        UpdateBand::run($band, $data);

        Notification::make()
            ->success()
            ->title('Band profile updated')
            ->send();
    }
}
