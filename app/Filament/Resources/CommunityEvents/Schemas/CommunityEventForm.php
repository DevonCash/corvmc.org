<?php

namespace App\Filament\Resources\CommunityEvents\Schemas;

use App\Models\CommunityEvent;
use App\Models\User;
use App\Data\VenueLocationData;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Card;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Fieldset;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CommunityEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Event Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                Select::make('event_type')
                                    ->label('Event Type')
                                    ->options([
                                        CommunityEvent::TYPE_PERFORMANCE => 'Performance',
                                        CommunityEvent::TYPE_WORKSHOP => 'Workshop',
                                        CommunityEvent::TYPE_OPEN_MIC => 'Open Mic',
                                        CommunityEvent::TYPE_COLLABORATIVE_SHOW => 'Collaborative Show',
                                        CommunityEvent::TYPE_ALBUM_RELEASE => 'Album Release',
                                    ])
                                    ->required()
                                    ->default(CommunityEvent::TYPE_PERFORMANCE),
                            ]),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('start_time')
                                    ->required()
                                    ->native(false)
                                    ->live(onBlur: true),

                                DateTimePicker::make('end_time')
                                    ->native(false)
                                    ->after('start_time'),
                            ]),
                    ]),

                Section::make('Venue Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('venue_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                Select::make('visibility')
                                    ->options([
                                        CommunityEvent::VISIBILITY_PUBLIC => 'Public',
                                        CommunityEvent::VISIBILITY_MEMBERS_ONLY => 'Members Only',
                                    ])
                                    ->required()
                                    ->default(CommunityEvent::VISIBILITY_PUBLIC),
                            ]),

                        Textarea::make('venue_address')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->helperText('Please provide a complete address including street, city, and state'),

                        Actions::make([
                            Action::make('calculate_distance')
                                ->label('Calculate Distance from Corvallis')
                                ->icon('tabler-map-pin')
                                ->action(function ($livewire, $get, $set) {
                                    $venueName = $get('venue_name');
                                    $venueAddress = $get('venue_address');

                                    if (empty($venueName) || empty($venueAddress)) {
                                        Notification::make()
                                            ->title('Please fill in venue name and address first')
                                            ->warning()
                                            ->send();
                                        return;
                                    }

                                    try {
                                        $location = VenueLocationData::create($venueName, $venueAddress)
                                            ->calculateDistance();

                                        if ($location->distance_from_corvallis !== null) {
                                            $set('distance_from_corvallis', $location->distance_from_corvallis);

                                            $warning = $location->getDistanceWarning();
                                            if ($warning) {
                                                Notification::make()
                                                    ->title('Distance Warning')
                                                    ->body($warning)
                                                    ->warning()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('Distance calculated successfully')
                                                    ->body("Driving time: {$location->getDrivingTimeDisplay()}")
                                                    ->success()
                                                    ->send();
                                            }
                                        } else {
                                            Notification::make()
                                                ->title('Could not calculate distance')
                                                ->body('Please verify the address is correct')
                                                ->warning()
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        Log::error($e);
                                        Notification::make()
                                            ->title('Error calculating distance')
                                            ->body('Please try again or contact support')
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ]),

                        TextInput::make('distance_from_corvallis')
                            ->label('Distance from Corvallis (minutes)')
                            ->numeric()
                            ->readonly()
                            ->helperText('Calculated driving time in minutes'),
                    ]),

                Section::make('Ticketing Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ticket_url')
                                    ->label('Ticket URL')
                                    ->url()
                                    ->maxLength(255),

                                TextInput::make('ticket_price')
                                    ->label('Ticket Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->step(0.01),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Media')
                    ->schema([
                        FileUpload::make('poster')
                            ->label('Event Poster')
                            ->image()
                            ->maxSize(2048)
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('8.5:11')
                            ->imageResizeTargetWidth('850')
                            ->imageResizeTargetHeight('1100')
                            ->directory('community-events/posters')
                            ->visibility('public'),
                    ])
                    ->collapsible(),

                Section::make('Administration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('organizer_id')
                                    ->label('Organizer')
                                    ->relationship('organizer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(Auth::id()),

                                Select::make('status')
                                    ->options([
                                        CommunityEvent::STATUS_PENDING => 'Pending',
                                        CommunityEvent::STATUS_APPROVED => 'Approved',
                                        CommunityEvent::STATUS_REJECTED => 'Rejected',
                                        CommunityEvent::STATUS_CANCELLED => 'Cancelled',
                                    ])
                                    ->required()
                                    ->default(CommunityEvent::STATUS_PENDING),
                            ]),

                        Placeholder::make('organizer_trust_info')
                            ->label('Organizer Trust Level')
                            ->content(function ($get) {
                                $organizerId = $get('organizer_id');
                                if (!$organizerId) return 'Select an organizer to see trust information';

                                $organizer = User::find($organizerId);
                                if (!$organizer) return 'Organizer not found';

                                $trustInfo = \App\Actions\Trust\GetTrustLevelInfo::run($organizer, 'App\\Models\\CommunityEvent');
                                $badge = \App\Actions\Trust\GetTrustBadge::run($organizer, 'App\\Models\\CommunityEvent');

                                $content = "Trust Level: {$trustInfo['level']} ({$trustInfo['points']} points)";

                                if ($badge) {
                                    $content .= "\nBadge: {$badge['label']}";
                                }

                                if ($trustInfo['next_level']) {
                                    $content .= "\nNext Level: {$trustInfo['next_level']} ({$trustInfo['points_needed']} points needed)";
                                }

                                return $content;
                            }),

                        DateTimePicker::make('published_at')
                            ->label('Published At')
                            ->native(false)
                            ->visible(fn ($get) => $get('status') === CommunityEvent::STATUS_APPROVED),
                    ])
                    ->visible(fn () => Auth::user()->can('manage community events'))
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
