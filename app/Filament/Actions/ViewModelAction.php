<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class ViewModelAction
{
    /**
     * Create an icon-button action that links to the Filament resource
     * view page for a given model instance.
     *
     * Usage:
     *   ViewModelAction::make(fn (LineItem $record) => $record->product())
     */
    public static function make(callable $resolveModel, ?string $name = 'view_model'): Action
    {
        return Action::make($name)
            ->label('View')
            ->icon('tabler-external-link')
            ->iconButton()
            ->url(fn ($record) => static::urlFor($resolveModel($record)))
            ->visible(fn ($record) => static::urlFor($resolveModel($record)) !== null)
            ->openUrlInNewTab();
    }

    /**
     * Resolve the Filament resource URL for a model instance.
     */
    public static function urlFor(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        foreach (Filament::getResources() as $resource) {
            if ($model instanceof ($resource::getModel())) {
                return $resource::getUrl('view', ['record' => $model]);
            }
        }

        return null;
    }
}
