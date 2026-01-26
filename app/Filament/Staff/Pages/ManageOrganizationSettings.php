<?php

namespace App\Filament\Staff\Pages;

use App\Actions\GoogleCalendar\BulkSyncReservationsToGoogleCalendar;
use App\Models\User;
use App\Settings\CommunityCalendarSettings;
use App\Settings\EquipmentSettings;
use App\Settings\FooterSettings;
use App\Settings\GoogleCalendarSettings;
use App\Settings\OrganizationSettings;
use App\Settings\ReservationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * @property Form $form
 */
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
        $equipmentSettings = app(EquipmentSettings::class);
        $communityCalendarSettings = app(CommunityCalendarSettings::class);
        $googleCalendarSettings = app(GoogleCalendarSettings::class);
        $reservationSettings = app(ReservationSettings::class);

        $this->form->fill([
            'name' => $settings->name,
            'description' => $settings->description,
            'ein' => $settings->ein,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'footer_links' => $footerSettings->getLinks(),
            'social_links' => $footerSettings->getSocialLinks(),
            'enable_equipment_features' => $equipmentSettings->enable_equipment_features,
            'enable_rental_features' => $equipmentSettings->enable_rental_features,
            'enable_community_calendar' => $communityCalendarSettings->enable_community_calendar,
            'enable_google_calendar_sync' => $googleCalendarSettings->enable_google_calendar_sync,
            'google_calendar_id' => $googleCalendarSettings->google_calendar_id,
            'reservation_buffer_minutes' => $reservationSettings->buffer_minutes,
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

                Section::make('Features')
                    ->schema([
                        Forms\Components\Toggle::make('enable_equipment_features')
                            ->label('Enable Equipment Features')
                            ->helperText('When enabled, the equipment section appears in navigation. When disabled, all equipment features are hidden.')
                            ->default(false)
                            ->live(),

                        Forms\Components\Toggle::make('enable_rental_features')
                            ->label('Enable Equipment Rental Features')
                            ->helperText('When enabled, members can checkout and return equipment through the system. When disabled, only the equipment catalog view is available.')
                            ->default(false)
                            ->visible(fn (callable $get) => $get('enable_equipment_features')),

                        Forms\Components\Toggle::make('enable_community_calendar')
                            ->label('Enable Community Calendar')
                            ->helperText('When enabled, members can submit and view community events on the calendar. When disabled, the community calendar is hidden from navigation.')
                            ->default(false),
                    ]),

                Section::make('Google Calendar Integration')
                    ->description('Sync practice space reservations to a Google Calendar')
                    ->schema([
                        Forms\Components\Toggle::make('enable_google_calendar_sync')
                            ->label('Enable Google Calendar Sync')
                            ->helperText('Automatically sync practice space reservations to Google Calendar')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('google_calendar_id')
                            ->label('Google Calendar ID')
                            ->helperText('The ID of the Google Calendar to sync to (e.g., your-calendar@group.calendar.google.com)')
                            ->placeholder('calendar-id@group.calendar.google.com')
                            ->visible(fn (callable $get) => $get('enable_google_calendar_sync'))
                            ->required(fn (callable $get) => $get('enable_google_calendar_sync')),

                        Forms\Components\Placeholder::make('service_account_info')
                            ->label('Service Account Setup')
                            ->content('Place your Google service account JSON key file at: storage/app/google-calendar-service-account.json. Set the path in your .env file: GOOGLE_CALENDAR_CREDENTIALS_PATH')
                            ->visible(fn (callable $get) => $get('enable_google_calendar_sync')),
                    ]),

                Section::make('Reservation Settings')
                    ->description('Configure practice space reservation behavior')
                    ->schema([
                        Forms\Components\TextInput::make('reservation_buffer_minutes')
                            ->label('Buffer Time Between Reservations')
                            ->helperText('Minutes of gap required between reservations (e.g., 15 means a 2-3pm booking blocks 1:45pm-3:15pm)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60)
                            ->default(0)
                            ->suffix('minutes'),
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
                                    ->maxLength(255)
                                    ->rules([
                                        function () {
                                            return function (string $attribute, $value, \Closure $fail) {
                                                if (empty($value)) {
                                                    return;
                                                }

                                                // Accept relative URLs (starting with /)
                                                if (str_starts_with($value, '/')) {
                                                    return;
                                                }

                                                // Accept fragment URLs (starting with #)
                                                if (str_starts_with($value, '#')) {
                                                    return;
                                                }

                                                // Accept absolute URLs
                                                if (filter_var($value, FILTER_VALIDATE_URL)) {
                                                    return;
                                                }

                                                $fail('The URL must be a valid absolute URL, relative path starting with /, or fragment starting with #.');
                                            };
                                        },
                                    ]),
                            ])
                            ->columns(2)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Url'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(3),

                        Forms\Components\Repeater::make('social_links')
                            ->label('Social Media Links')
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Url'),
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
                                    ->maxLength(255)
                                    ->rules([
                                        function () {
                                            return function (string $attribute, $value, \Closure $fail) {
                                                if (empty($value)) {
                                                    return;
                                                }

                                                // Accept relative URLs (starting with /)
                                                if (str_starts_with($value, '/')) {
                                                    return;
                                                }

                                                // Accept fragment URLs (starting with #)
                                                if (str_starts_with($value, '#')) {
                                                    return;
                                                }

                                                // Accept absolute URLs
                                                if (filter_var($value, FILTER_VALIDATE_URL)) {
                                                    return;
                                                }

                                                $fail('The URL must be a valid absolute URL, relative path starting with /, or fragment starting with #.');
                                            };
                                        },
                                    ]),
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

    protected function getHeaderActions(): array
    {
        $googleCalendarSettings = app(GoogleCalendarSettings::class);

        if (! $googleCalendarSettings->enable_google_calendar_sync) {
            return [];
        }

        return [
            Action::make('bulkSyncGoogleCalendar')
                ->label('Sync All Reservations')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync All Reservations to Google Calendar')
                ->modalDescription('This will sync all future/current non-cancelled reservations to Google Calendar. Reservations already synced will be skipped.')
                ->modalSubmitActionLabel('Sync Now')
                ->action(function () {
                    $result = BulkSyncReservationsToGoogleCalendar::run();

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Bulk sync completed')
                            ->body("{$result['synced']} reservations synced, {$result['failed']} failed, {$result['skipped']} skipped")
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Bulk sync failed')
                            ->body($result['message'])
                            ->send();
                    }
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(OrganizationSettings::class);
        $footerSettings = app(FooterSettings::class);
        $equipmentSettings = app(EquipmentSettings::class);
        $communityCalendarSettings = app(CommunityCalendarSettings::class);
        $googleCalendarSettings = app(GoogleCalendarSettings::class);
        $reservationSettings = app(ReservationSettings::class);

        $settings->name = $data['name'];
        $settings->description = $data['description'];
        $settings->ein = $data['ein'];
        $settings->address = $data['address'];
        $settings->phone = $data['phone'];
        $settings->email = $data['email'];

        $footerSettings->links = $data['footer_links'] ?? [];
        $footerSettings->social_links = $data['social_links'] ?? [];

        $equipmentSettings->enable_equipment_features = $data['enable_equipment_features'] ?? false;
        $equipmentSettings->enable_rental_features = $data['enable_rental_features'] ?? false;
        $communityCalendarSettings->enable_community_calendar = $data['enable_community_calendar'] ?? false;

        $googleCalendarSettings->enable_google_calendar_sync = $data['enable_google_calendar_sync'] ?? false;
        $googleCalendarSettings->google_calendar_id = $data['google_calendar_id'] ?? null;

        $reservationSettings->buffer_minutes = (int) ($data['reservation_buffer_minutes'] ?? 0);

        $settings->save();
        $footerSettings->save();
        $equipmentSettings->save();
        $communityCalendarSettings->save();
        $googleCalendarSettings->save();
        $reservationSettings->save();

        Notification::make()
            ->success()
            ->title('Organization settings updated')
            ->send();
    }
}
