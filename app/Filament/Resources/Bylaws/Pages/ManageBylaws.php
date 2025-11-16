<?php

namespace App\Filament\Resources\Bylaws\Pages;

use App\Filament\Resources\Bylaws\BylawsResource;
use App\Settings\BylawsSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

class ManageBylaws extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = BylawsResource::class;

    protected static ?string $title = 'Organization Bylaws';

    protected string $view = 'filament.resources.bylaws.pages.manage-bylaws';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(BylawsSettings::class);
        $this->form->fill([
            'content' => $settings->content,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                MarkdownEditor::make('content')
                    ->label('Bylaws Content')
                    ->required()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'link',
                        'heading',
                        'bulletList',
                        'orderedList',
                        'blockquote',
                        'codeBlock',
                        'table',
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn () => route('bylaws'))
                ->openUrlInNewTab(),
            Action::make('save')
                ->label('Save Changes')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(BylawsSettings::class);
        $settings->content = $data['content'];
        $settings->last_updated_by = auth()->id();
        $settings->last_updated_at = now()->toIso8601String();
        $settings->save();

        Notification::make()
            ->success()
            ->title('Bylaws updated')
            ->body('The organization bylaws have been successfully updated.')
            ->send();
    }
}
