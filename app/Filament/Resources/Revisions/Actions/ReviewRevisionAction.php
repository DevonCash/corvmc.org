<?php

namespace App\Filament\Resources\Revisions\Actions;

use App\Actions\Revisions\ApproveRevision;
use App\Actions\Revisions\RejectRevision;
use App\Filament\Actions\Action;
use App\Models\Revision;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;

class ReviewRevisionAction
{
    public static function make(): Action
    {
        return Action::make('reviewRevision')
            ->label(fn (?Revision $record) => $record?->isPending() ? 'Review' : 'View Review')
            ->icon('tabler-clipboard-check')
            ->iconPosition(IconPosition::Before)
            ->color(fn (?Revision $record) => match ($record?->status) {
                Revision::STATUS_APPROVED => 'success',
                Revision::STATUS_REJECTED => 'danger',
                default => 'primary',
            })
            ->authorize('update')
            ->slideOver()
            ->modalHeading(fn (Revision $record): string => static::getModalHeading($record))
            ->modalDescription(fn (Revision $record): string => static::getModalDescription($record))
            ->modalWidth('4xl')
            ->schema([
                ViewField::make('review_decision')
                    ->label('')
                    ->view('filament.resources.revisions.review-decision')
                    ->viewData(fn (Revision $record): array => [
                        'revision' => $record,
                    ])
                    ->visible(fn (Revision $record) => $record->isReviewed()),

                ViewField::make('changes_diff')
                    ->label('Proposed Changes')
                    ->view('filament.resources.revisions.diff-viewer')
                    ->viewData(fn (Revision $record): array => [
                        'changes' => $record->getChanges(),
                        'modelType' => $record->getModelTypeName(),
                        'modelTitle' => $record->getRevisionableTitle(),
                    ]),
            ])
            ->modalFooterActions(fn (Revision $record) => $record->isPending() ? [
                static::approveAction(),
                static::rejectAction(),
            ] : [])
            ->modalFooterActionsAlignment('end')
            ->modalCancelAction(fn (Revision $record) => $record->isPending() ? false : true)
            ->modalCancelActionLabel('Close');
    }

    protected static function getModalHeading(Revision $record): string
    {
        if ($record->isPending()) {
            return "Review Revision #{$record->id}";
        }

        $status = $record->status === Revision::STATUS_APPROVED ? 'Approved' : 'Rejected';

        return "{$status} Revision #{$record->id}";
    }

    protected static function getModalDescription(Revision $record): string
    {
        $submitter = $record->submittedBy->name ?? 'Unknown';
        $submittedAt = $record->created_at->diffForHumans();

        $description = "Submitted by {$submitter} {$submittedAt}";

        if ($record->submission_reason) {
            $description .= " — \"{$record->submission_reason}\"";
        }

        return $description;
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('tabler-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Revision')
            ->modalDescription('Are you sure you want to approve this revision? The changes will be applied immediately.')
            ->modalSubmitActionLabel('Approve')
            ->action(function (Revision $record) {
                try {
                    ApproveRevision::run($record, auth()->user());

                    Notification::make()
                        ->title('Revision approved')
                        ->body('The changes have been applied successfully.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to approve revision')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw $e;
                }
            });
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('tabler-x')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject Revision')
            ->modalDescription('Please provide a reason for rejecting this revision. The submitter will be notified.')
            ->modalSubmitActionLabel('Reject')
            ->schema([
                Textarea::make('reason')
                    ->label('Rejection Reason')
                    ->required()
                    ->rows(3)
                    ->placeholder('Explain why this revision is being rejected...'),
            ])
            ->action(function (Revision $record, array $data) {
                try {
                    RejectRevision::run($record, auth()->user(), $data['reason']);

                    Notification::make()
                        ->title('Revision rejected')
                        ->body('The submitter has been notified.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to reject revision')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw $e;
                }
            });
    }
}
