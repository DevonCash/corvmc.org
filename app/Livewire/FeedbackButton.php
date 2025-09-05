<?php

namespace App\Livewire;

use App\Services\GitHubService;
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
use Livewire\Component;

class FeedbackButton extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public string $pageUrl = '';

    public function feedbackAction(): Action
    {
        return Action::make('feedback')
            ->label('Feedback')
            ->icon('tabler-message-2')
            ->color('gray')
            ->size('sm')
            ->tooltip('Submit Feedback')
            ->modalHeading('Submit Feedback')
            ->modalDescription('Help us improve the site! Submit bug reports, feature requests, or general suggestions.')
            ->modalWidth('2xl')
            ->schema([
                TextInput::make('title')
                    ->label('Title')
                    ->placeholder('Brief summary of your feedback')
                    ->required()
                    ->maxLength(255),

                Grid::make(2)->schema([
                    Select::make('category')
                        ->label('Category')
                        ->options([
                            'bug' => 'Bug Report',
                            'feature' => 'Feature Request',
                            'improvement' => 'Improvement Suggestion',
                            'general' => 'General Feedback',
                        ])
                        ->default('general')
                        ->required(),

                    Select::make('priority')
                        ->label('Severity')
                        ->options([
                            'low' => 'Minor – does not affect usage',
                            'medium' => 'Major – affects usage but has a workaround',
                            'high' => 'Critical – prevents normal use',
                        ])
                        ->default('medium')
                        ->required(),
                ]),

                Textarea::make('description')
                    ->label('Description')
                    ->placeholder('Please provide detailed information about your feedback, suggestion, or issue...')
                    ->required()
                    ->rows(6)
                    ->maxLength(2000),

                TextInput::make('page_url')
                    ->label('Page URL (debug)')
                    ->default($this->pageUrl)
                    ->hidden(),
            ])
            ->action(function (array $data) {
                // Add user information automatically
                $user = auth()->user();
                if ($user) {
                    $data['user_id'] = $user->id;
                }

                // Add browser info and environment automatically
                $data['browser_info'] = request()->header('User-Agent') ?? '';
                $data['environment'] = app()->environment();

                $gitHubService = \GitHubService::getFacadeRoot();
                $result = $gitHubService->createIssue($data);

                if ($result['success']) {
                    Notification::make()
                        ->title('Feedback Submitted Successfully!')
                        ->body("Your feedback has been submitted as GitHub issue #{$result['issue_number']}. Thank you!")
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('view')
                                ->label('View Issue')
                                ->url($result['url'])
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                } else {
                    Notification::make()
                        ->title('Failed to Submit Feedback')
                        ->body($result['error'] ?? 'There was an error submitting your feedback. Please try again or contact support.')
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public function render()
    {
        return view('livewire.feedback-button');
    }
}
