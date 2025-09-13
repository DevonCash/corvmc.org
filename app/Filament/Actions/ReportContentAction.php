<?php

namespace App\Filament\Actions;

use App\Facades\ReportService;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class ReportContentAction
{
    public static function make(string $name = 'report'): Action
    {
        return Action::make($name)
            ->label('Report Content')
            ->icon('heroicon-o-flag')
            ->color('danger')
            ->visible(
                fn(Model $record) =>
                // Don't show if user already reported this content
                !$record->hasBeenReportedBy(auth()->user())
            )
            ->schema(fn(Model $record) => [
                Select::make('reason')
                    ->label('Reason for Report')
                    ->options(function () use ($record) {
                        $reasons = Report::getReasonsForType(get_class($record));
                        return collect($reasons)
                            ->mapWithKeys(fn($reason) => [$reason => Report::REASONS[$reason]])
                            ->toArray();
                    })
                    ->required()
                    ->reactive(),

                Textarea::make('custom_reason')
                    ->label('Additional Details')
                    ->placeholder('Please provide additional context for your report...')
                    ->visible(fn($get) => $get('reason') === 'other')
                    ->required(fn($get) => $get('reason') === 'other')
                    ->rows(3),

                Textarea::make('details')
                    ->label('Additional Details (Optional)')
                    ->placeholder('Any additional information that might help moderators...')
                    ->visible(fn($get) => $get('reason') !== 'other')
                    ->rows(3),
            ])
            ->action(function (Model $record, array $data): void {
                try {

                    $customReason = $data['reason'] === 'other'
                        ? $data['custom_reason']
                        : ($data['details'] ?? null);

                    $report = ReportService::submitReport(
                        $record,
                        auth()->user(),
                        $data['reason'],
                        $customReason
                    );

                    Notification::make()
                        ->title('Report Submitted')
                        ->body("Thank you for reporting this {$record->getReportableType()}. We'll review it shortly.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Report Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalHeading(fn(Model $record) => "Report {$record->getReportableType()}")
            ->modalDescription('Please help us understand why you\'re reporting this content. False reports may impact your ability to report in the future.')
            ->modalSubmitActionLabel('Submit Report');
    }
}
