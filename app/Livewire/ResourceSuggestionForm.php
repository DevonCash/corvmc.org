<?php

namespace App\Livewire;

use App\Models\ResourceList;
use App\Notifications\ResourceSuggestionNotification;
use App\Settings\OrganizationSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Livewire\Component;

/**
 * @property \Filament\Schemas\Components\Form $form
 */
class ResourceSuggestionForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Schema $form): Schema
    {
        $categories = ResourceList::published()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();

        return $form
            ->schema([
                TextInput::make('resource_name')
                    ->label('Resource/Business Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Melody Music Shop'),

                Select::make('category')
                    ->label('Category')
                    ->options($categories + ['other' => 'Other / New Category'])
                    ->placeholder('Select a category...'),

                TextInput::make('new_category')
                    ->label('Suggest New Category')
                    ->maxLength(255)
                    ->placeholder('If "Other" selected above')
                    ->visible(fn ($get) => $get('category') === 'other'),

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
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $validated = $this->form->getState();

        // Resolve category name
        if ($validated['category'] === 'other') {
            $validated['category_name'] = $validated['new_category'] ?? 'New Category';
        } elseif ($validated['category']) {
            $validated['category_name'] = ResourceList::find($validated['category'])?->name ?? 'Unknown';
        } else {
            $validated['category_name'] = 'Not specified';
        }

        // Log the submission
        logger('Resource suggestion submitted', $validated);

        // Send email notification to organization
        $staffEmail = app(OrganizationSettings::class)->email;

        LaravelNotification::route('mail', $staffEmail)
            ->notify(new ResourceSuggestionNotification($validated));

        // Show success notification
        Notification::make()
            ->title('Suggestion Received!')
            ->body('Thank you for suggesting a resource! We\'ll review it and add it if appropriate.')
            ->success()
            ->send();

        // Reset the form
        $this->form->fill([]);
    }

    public function render()
    {
        return view('livewire.resource-suggestion-form');
    }
}
