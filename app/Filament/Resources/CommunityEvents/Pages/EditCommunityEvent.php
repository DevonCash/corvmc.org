<?php

namespace App\Filament\Resources\CommunityEvents\Pages;

use App\Filament\Resources\CommunityEvents\CommunityEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunityEvent extends EditRecord
{
    protected static string $resource = CommunityEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}