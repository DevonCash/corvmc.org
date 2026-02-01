<?php

namespace App\Filament\Staff\Resources\Events\RelationManagers;

use App\Filament\Staff\Resources\Events\Actions\InviteBandOwnerAction;
use CorvMC\Events\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PerformersRelationManager extends RelationManager
{
    protected static string $relationship = 'performers';

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $table
            ->description(function () use ($event) {
                if (! $event->start_datetime || ! $event->end_datetime) {
                    return null;
                }

                $eventDurationMinutes = $event->start_datetime->diffInMinutes($event->end_datetime);
                $totalSetLength = (int) ($event->performers()->sum('event_bands.set_length') ?? 0);
                $performerCount = $event->performers()->count();

                if ($totalSetLength > $eventDurationMinutes) {
                    $overageMinutes = $totalSetLength - $eventDurationMinutes;

                    return new HtmlString(
                        '<span class="text-warning-600 dark:text-warning-400 font-medium">'.
                        "Set lengths total {$totalSetLength} min, exceeding event duration ({$eventDurationMinutes} min) by {$overageMinutes} min".
                        '</span>'
                    );
                }

                if ($performerCount > 0 && $totalSetLength > 0) {
                    $remainingMinutes = $eventDurationMinutes - $totalSetLength;
                    $changeoverTime = (int) floor($remainingMinutes / $performerCount);

                    return "{$totalSetLength} min of sets, ~{$changeoverTime} min changeover per band";
                }

                return null;
            })
            ->reorderable('event_bands.order')
            ->defaultSort('event_bands.order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
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
                        return 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7C3AED&background=F3E8FF&size=120';
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
                            ->rules(['phone:US,AUTO'])
                            ->validationMessages([
                                'phone' => 'Please enter a valid phone number',
                            ])
                            ->placeholder('(555) 123-4567'),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        // Ensure touring bands have no owner
                        $data['owner_id'] = null;
                        $data['visibility'] = 'private';

                        return $data;
                    })
                    ->modalHeading('Add Touring Band')
                    ->modalDescription('Create a profile for a touring band that will perform at this event.')
                    ->modalSubmitActionLabel('Add Band'),
            ])
            ->recordActions([
                InviteBandOwnerAction::make(),
            ]);
    }
}
