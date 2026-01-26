<?php

namespace App\Livewire;

use App\Models\ResourceList;
use App\Notifications\ResourceSuggestionNotification;
use App\Settings\OrganizationSettings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Livewire\Component;

class ResourceSuggestionForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function suggestResourceAction(): Action
    {
        $categories = ResourceList::published()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();

        return Action::make('suggestResource')
            ->label('Suggest a Resource')
            ->icon('tabler-plus')
            ->size('lg')
            ->color('primary')
            ->modalHeading('Suggest a Local Resource')
            ->modalDescription('Know a great local business or service? Help us build this directory by sharing your recommendation.')
            ->modalSubmitActionLabel('Submit Suggestion')
            ->modalWidth('xl')
            ->form([
                Section::make('Resource Information')
                    ->schema([
                        TextInput::make('resource_name')
                            ->label('Resource/Business Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Melody Music Shop'),

                        Grid::make(2)->schema([
                            Select::make('category')
                                ->label('Category')
                                ->options($categories + ['other' => 'Other / New Category'])
                                ->placeholder('Select a category...')
                                ->live(),

                            TextInput::make('new_category')
                                ->label('Suggest New Category')
                                ->maxLength(255)
                                ->placeholder('Enter category name')
                                ->visible(fn ($get) => $get('category') === 'other'),
                        ]),

                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('What does this resource offer? Why would it be helpful to musicians?'),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('contact_name')
                                ->label('Contact Name')
                                ->maxLength(255),

                            TextInput::make('contact_phone')
                                ->label('Contact Phone')
                                ->tel()
                                ->maxLength(20),
                        ]),

                        TextInput::make('address')
                            ->label('Address')
                            ->maxLength(255)
                            ->placeholder('Street address, city'),
                    ]),

                Section::make('Your Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('submitter_name')
                                ->label('Your Name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('submitter_email')
                                ->label('Your Email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->helperText('We may contact you if we have questions'),
                        ]),
                    ]),
            ])
            ->action(function (array $data): void {
                // Resolve category name
                if ($data['category'] === 'other') {
                    $data['category_name'] = $data['new_category'] ?? 'New Category';
                } elseif ($data['category']) {
                    $data['category_name'] = ResourceList::find($data['category'])?->name ?? 'Unknown';
                } else {
                    $data['category_name'] = 'Not specified';
                }

                // Log the submission
                logger('Resource suggestion submitted', $data);

                // Send email notification to organization
                $staffEmail = app(OrganizationSettings::class)->email;

                LaravelNotification::route('mail', $staffEmail)
                    ->notify(new ResourceSuggestionNotification($data));

                // Show success notification
                Notification::make()
                    ->title('Suggestion Received!')
                    ->body('Thank you for suggesting a resource! We\'ll review it and add it if appropriate.')
                    ->success()
                    ->send();
            });
    }

    public function render()
    {
        return view('livewire.resource-suggestion-form');
    }
}
