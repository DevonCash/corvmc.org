<?php

namespace App\Livewire;

use CorvMC\Membership\Notifications\ContactFormSubmissionNotification;
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
class ContactForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        // Pre-fill subject from query parameter if provided
        $topic = request()->query('topic');
        $this->form->fill([
            'subject' => $topic,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                Grid::make(2)->schema([
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(20),
                ]),

                Select::make('subject')
                    ->label('Subject')
                    ->required()
                    ->options([
                        'general' => 'General Inquiry',
                        'membership' => 'Membership Questions',
                        'practice_space' => 'Practice Space',
                        'performance' => 'Performance Inquiry',
                        'volunteer' => 'Volunteer Opportunities',
                        'donation' => 'Donations & Support',
                    ])
                    ->placeholder('Choose a topic...'),

                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(6)
                    ->maxLength(2000)
                    ->placeholder('Tell us more about your inquiry...'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $validated = $this->form->getState();

        // Log the contact submission
        logger('Contact form submission', $validated);

        // Send email notification to organization contact email
        $staffEmail = app(OrganizationSettings::class)->email;

        LaravelNotification::route('mail', $staffEmail)
            ->notify(new ContactFormSubmissionNotification($validated));

        // Show success notification
        Notification::make()
            ->title('Message Sent!')
            ->body('Thank you for your message! We\'ll get back to you soon.')
            ->success()
            ->send();

        // Reset the form
        $this->form->fill([]);
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
