<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Settings\OrganizationSettings;
use App\Settings\FooterSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class ManageOrganizationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-adjustments';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-organization-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(OrganizationSettings::class);
        $footerSettings = app(FooterSettings::class);

        $this->form->fill([
            'name' => $settings->name,
            'description' => $settings->description,
            'ein' => $settings->ein,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'footer_links' => $footerSettings->getLinks(),
            'social_links' => $footerSettings->getSocialLinks(),
        ]);
    }

    public static function canAccess(): bool
    {
        return User::me()->can('manage site settings');
    }
    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Organization Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Organization Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500),

                        Forms\Components\TextInput::make('ein')
                            ->label('EIN (Employer Identification Number)')
                            ->helperText('Enter 9 digits without dashes (e.g., 123456789)')
                            ->required()
                            ->length(9)
                            ->regex('/^\d{9}$/')
                            ->validationMessages([
                                'regex' => 'EIN must be exactly 9 digits.',
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Address')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('Contact Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Footer Links')
                    ->schema([
                        Forms\Components\Repeater::make('footer_links')
                            ->label('Footer Links')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Label')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->required()
                                    ->url()
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Url')
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(3),

                        Forms\Components\Repeater::make('social_links')
                            ->label('Social Media Links')
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Url')
                            ])
                            ->schema([
                                Forms\Components\Select::make('icon')
                                    ->label('Icon')
                                    ->required()
                                    ->options([
                                        'tabler:brand-x' => 'X (Twitter)',
                                        'tabler:brand-facebook' => 'Facebook',
                                        'tabler:brand-instagram' => 'Instagram',
                                        'tabler:brand-pinterest' => 'Pinterest',
                                        'tabler:brand-youtube' => 'YouTube',
                                        'tabler:brand-linkedin' => 'LinkedIn',
                                        'tabler:brand-tiktok' => 'TikTok',
                                        'tabler:brand-discord' => 'Discord',
                                    ]),
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->required()
                                    ->url()
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(4),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(OrganizationSettings::class);
        $footerSettings = app(FooterSettings::class);

        $settings->name = $data['name'];
        $settings->description = $data['description'];
        $settings->ein = $data['ein'];
        $settings->address = $data['address'];
        $settings->phone = $data['phone'];
        $settings->email = $data['email'];

        $footerSettings->links = $data['footer_links'] ?? [];
        $footerSettings->social_links = $data['social_links'] ?? [];

        $settings->save();
        $footerSettings->save();

        Notification::make()
            ->success()
            ->title('Organization settings updated')
            ->send();
    }
}
