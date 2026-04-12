<?php

namespace App\Filament\Tables\Columns;

use App\Filament\Support\ResourceUrlResolver;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class MorphColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        // The column name is the relation name (e.g., 'chargeable')
        // This shows the actual related record, not just the type
        $this->getStateUsing(function (Model $record) {
            $relationName = $this->getName();

            // Check if the relation is loaded
            if (! $record->relationLoaded($relationName)) {
                $record->load($relationName);
            }

            $related = $record->{$relationName};

            if (! $related) {
                return null;
            }

            // Get a display name for the related model
            return $this->getRelatedModelDisplayName($related);
        });

        // Make the column clickable to view the related model
        $this->url(function (Model $record) {
            $relationName = $this->getName();

            // Check if the relation is loaded
            if (! $record->relationLoaded($relationName)) {
                $record->load($relationName);
            }

            $related = $record->{$relationName};

            if (! $related) {
                return null;
            }

            // Use the ResourceUrlResolver to get the URL
            return ResourceUrlResolver::getUrl($related);
        });

        // Add an icon to indicate it's clickable
        $this->icon('tabler-external-link')
            ->iconPosition('after');

        // Color the text to indicate it's a link
        $this->color('primary');
    }

    protected function getRelatedModelDisplayName(Model $model): string
    {
        // Try various methods to get a display name
        if (method_exists($model, 'getFilamentDisplayName')) {
            return $model->getFilamentDisplayName();
        }

        if (method_exists($model, 'getDisplayName')) {
            return $model->getDisplayName();
        }

        if (method_exists($model, 'getTitle')) {
            return $model->getTitle();
        }

        // For common models, use appropriate fields
        $className = class_basename($model);

        return "{$className} #{$model->id}";
    }
}
