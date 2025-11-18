<?php

namespace App\Filament\Actions;

use App\Contracts\Reportable;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReportContentAction
{
    public static function make(string $name = 'report'): Action
    {
        return Action::make($name)
            ->label('Report Content')
            ->icon('tabler-flag')
            ->color('danger')
            ->visible(
                fn (Model $record) =>
                // Don't show if user already reported this content
                $record instanceof Reportable && ! $record->hasBeenReportedBy(Auth::user())
            )
            ->schema(fn (Model $record) => [
                Select::make('reason')
                    ->label('Reason for Report')
                    ->options(function () use ($record) {
                        $reasons = Report::getReasonsForType(get_class($record));

                        return collect($reasons)
                            ->mapWithKeys(fn ($reason) => [$reason => Report::REASONS[$reason]])
                            ->toArray();
                    })
                    ->required()
                    ->reactive(),

                Textarea::make('custom_reason')
                    ->label('Additional Details')
                    ->placeholder('Please provide additional context for your report...')
                    ->visible(fn ($get) => $get('reason') === 'other')
                    ->required(fn ($get) => $get('reason') === 'other')
                    ->rows(3),

                Textarea::make('details')
                    ->label('Additional Details (Optional)')
                    ->placeholder('Any additional information that might help moderators...')
                    ->visible(fn ($get) => $get('reason') !== 'other')
                    ->rows(3),
            ])
            ->action(function (Model $record, array $data): void {
                if (! $record instanceof Reportable) {
                    return;
                }

                $customReason = $data['reason'] === 'other'
                    ? $data['custom_reason']
                    : ($data['details'] ?? null);

                $report = \App\Actions\Reports\SubmitReport::run(
                    $record,
                    Auth::user(),
                    $data['reason'],
                    $customReason
                );

                Notification::make()
                    ->title('Report Submitted')
                    ->body("Thank you for reporting this {$record->getReportableType()}. We'll review it shortly.")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading(function (Model $record) {
                if ($record instanceof Reportable) {
                    return "Report {$record->getReportableType()}";
                }

                return 'Report Content';
            })
            ->modalDescription('Please help us understand why you\'re reporting this content. False reports may impact your ability to report in the future.')
            ->modalSubmitActionLabel('Submit Report');
    }
}
