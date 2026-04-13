<?php

namespace App\Filament\Actions\Bands;

use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Data\ContactData;
use CorvMC\Membership\Services\BandService;
use CorvMC\Moderation\Enums\Visibility;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;

/**
 * Filament Action for creating a new band.
 *
 * This action handles the UI concerns for band creation
 * and delegates business logic to the BandService.
 */
class CreateBandAction
{
    public static function make(): Action
    {
        return Action::make('create_band')
            ->label('Create Band')
            ->icon('tabler-guitar-pick')
            ->color('primary')
            ->requiresConfirmation(false)
            ->modalHeading('Create New Band')
            ->modalWidth('lg')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Band Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Band::class, 'name')
                            ->placeholder('Enter your band name'),

                        TextInput::make('hometown')
                            ->label('Location')
                            ->placeholder('City, State/Country')
                            ->maxLength(255)
                            ->helperText('Where is your band based?'),

                        RichEditor::make('bio')
                            ->label('Biography')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->maxLength(5000)
                            ->helperText('Tell us about your band (max 5000 characters)')
                            ->columnSpanFull(),
                    ])

            ])
            ->action(function (array $data) {
                $user = auth()->user();

                // Prepare contact data if provided
                if (!empty($data['contact'])) {
                    $contactData = array_filter([
                        'email' => $data['contact']['email'] ?? null,
                        'phone' => $data['contact']['phone'] ?? null,
                        'website' => $data['contact']['website'] ?? null,
                    ]);

                    if (!empty($contactData)) {
                        $data['contact'] = ContactData::from($contactData);
                    } else {
                        unset($data['contact']);
                    }
                }

                // Set visibility enum if provided
                if (isset($data['visibility'])) {
                    $data['visibility'] = Visibility::from($data['visibility']);
                }

                // Use service to create band with current user as owner
                $service = app(BandService::class);
                $band = $service->create($user, $data);

                Notification::make()
                    ->title('Band created successfully')
                    ->body("Your band '{$band->name}' has been created.")
                    ->success()
                    ->send();

                // Optionally redirect to band page
                if (method_exists($band, 'getUrl')) {
                    return redirect($band->getUrl());
                }
            });
    }
}
