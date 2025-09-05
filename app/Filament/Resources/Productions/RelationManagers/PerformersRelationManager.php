<?php

namespace App\Filament\Resources\Productions\RelationManagers;

use App\Filament\Resources\Bands\BandResource;
use App\Services\UserInvitationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class PerformersRelationManager extends RelationManager
{
    protected static string $relationship = 'performers';

    protected static ?string $relatedResource = BandResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('production_bands.order')
            ->defaultSort('production_bands.order')
            ->reorderRecordsTriggerAction(
                fn(Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Disable reordering' : 'Enable reordering'),
            )
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->imageSize(60)
                    ->grow(false)
                    ->defaultImageUrl(function ($record) {
                        return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7C3AED&background=F3E8FF&size=120';
                    }),

                TextColumn::make('name')
                    ->label('Band')
                    ->grow(false)
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(function ($record) {
                        $parts = [];

                        // Add location if available
                        if ($record->hometown) {
                            $parts[] = $record->hometown;
                        }

                        return implode(' • ', $parts);
                    }),
                SpatieTagsColumn::make('genre')
                    ->limitList(3)
                    ->grow(true)
                    ->type('genre'),

                TextInputColumn::make('set_length')
                    ->label('Set Length')
                    ->type('number')
                    ->grow(false)
                    ->rules(['min:0', 'integer']),

            ])
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->schema([
                        TextInput::make('name')
                            ->label('Band Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter band name'),

                        TextInput::make('hometown')
                            ->label('Location')
                            ->placeholder('City, State/Country')
                            ->maxLength(255),

                        Textarea::make('bio')
                            ->label('Biography')
                            ->placeholder('Brief description of the band...')
                            ->rows(3),

                        SpatieTagsInput::make('genres')
                            ->type('genre')
                            ->label('Musical Genres')
                            ->placeholder('Rock, Pop, Jazz, etc.'),

                        TextInput::make('contact.email')
                            ->label('Contact Email')
                            ->email()
                            ->placeholder('band@example.com'),

                        TextInput::make('contact.phone')
                            ->label('Contact Phone')
                            ->tel()
                            ->placeholder('(555) 123-4567'),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        // Ensure touring bands have no owner
                        $data['owner_id'] = null;
                        $data['visibility'] = 'private';

                        return $data;
                    })
                    ->modalHeading('Add Touring Band')
                    ->modalDescription('Create a profile for a touring band that will perform at this production.')
                    ->modalSubmitActionLabel('Add Band'),
            ])
            ->recordActions([
                Action::make('invite_band_owner')
                    ->label('Invite Owner')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn($record) => !$record->owner_id) // Only show for bands without owners (touring bands)
                    ->form([
                        TextInput::make('email')
                            ->label('Band Owner Email')
                            ->email()
                            ->required()
                            ->placeholder('bandleader@example.com')
                            ->helperText('We\'ll invite this person to join CMC and take ownership of this band.'),

                        TextInput::make('hometown')
                            ->label('Update Band Location (Optional)')
                            ->placeholder('City, State/Country')
                            ->helperText('Refine the band\'s location if needed.'),

                        Textarea::make('bio')
                            ->label('Update Band Bio (Optional)')
                            ->rows(3)
                            ->helperText('Add or refine the band\'s biography.'),
                    ])
                    ->action(function ($record, array $data) {
                        $invitationService = \UserInvitationService::getFacadeRoot();

                        // Prepare band data for updates
                        $bandData = [];
                        if (!empty($data['hometown'])) {
                            $bandData['hometown'] = $data['hometown'];
                        }
                        if (!empty($data['bio'])) {
                            $bandData['bio'] = $data['bio'];
                        }

                        try {
                            $result = $invitationService->inviteUserWithBand(
                                $data['email'],
                                $record->name,
                                $bandData,
                                ['band leader']
                            );

                            // Update the existing band record to have the new owner
                            $record->update([
                                'owner_id' => $result['user']->id,
                                'status' => $result['invited_user'] ? 'pending_owner_verification' : 'active'
                            ]);

                            // Add any updated band data
                            if (!empty($bandData)) {
                                $record->update($bandData);
                            }

                            $message = $result['invited_user']
                                ? "Invitation sent to {$data['email']} to own {$record->name}"
                                : "Band ownership transferred to existing member {$data['email']}";

                            Notification::make()
                                ->title('Band Owner Invited!')
                                ->body($message)
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('Failed to invite band owner: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading(fn($record) => "Invite Owner for {$record->name}")
                    ->modalDescription('Invite someone to join CMC and take ownership of this band profile.')
                    ->modalSubmitActionLabel('Send Invitation')
            ]);
    }
}
