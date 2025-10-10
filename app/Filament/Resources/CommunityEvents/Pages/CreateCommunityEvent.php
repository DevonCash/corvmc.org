<?php

namespace App\Filament\Resources\CommunityEvents\Pages;

use App\Filament\Resources\CommunityEvents\CommunityEventResource;
use App\Models\CommunityEvent;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateCommunityEvent extends CreateRecord
{
    protected static string $resource = CommunityEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the organizer to current user if not set
        if (empty($data['organizer_id'])) {
            $data['organizer_id'] = auth()->id();
        }

        // Determine approval workflow based on trust level
        $organizer = \App\Models\User::find($data['organizer_id']);

        if ($organizer) {
            $workflow = \App\Actions\Trust\DetermineApprovalWorkflow::run($organizer, 'App\\Models\\CommunityEvent');
            
            if ($workflow['auto_publish']) {
                $data['status'] = CommunityEvent::STATUS_APPROVED;
                $data['published_at'] = now();
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $workflow = \App\Actions\Trust\DetermineApprovalWorkflow::run($record->organizer, 'App\\Models\\CommunityEvent');

        if ($workflow['auto_publish']) {
            Notification::make()
                ->title('Event created and published!')
                ->body('Your event has been automatically approved and is now live.')
                ->success()
                ->send();
        } elseif ($workflow['review_priority'] === 'fast-track') {
            Notification::make()
                ->title('Event submitted for fast-track review')
                ->body('Your event will be reviewed within 24 hours.')
                ->info()
                ->send();
        } else {
            Notification::make()
                ->title('Event submitted for review')
                ->body('Your event will be reviewed within 72 hours.')
                ->info()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}