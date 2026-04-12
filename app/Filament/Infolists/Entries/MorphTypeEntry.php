<?php

namespace App\Filament\Infolists\Entries;

use App\Filament\Tables\Columns\MorphTypeColumn;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class MorphTypeEntry extends TextEntry
{
    protected array | \Closure $morphMap = [];
    
    protected bool $useAppMorphMap = true;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // The entry name is the relation name (e.g., 'chargeable')
        // The actual database column is relation_name + '_type' (e.g., 'chargeable_type')
        $this->getStateUsing(function (Model $record) {
            $relationName = $this->getName();
            $typeColumn = $relationName . '_type';
            
            return $record->{$typeColumn} ?? null;
        });
        
        // Format as a badge by default
        $this->badge();
        
        // Use the app's morph map by default
        $this->morphMap(fn () => $this->useAppMorphMap ? Relation::morphMap() : []);
        
        // Format the state to show a human-readable label
        $this->formatStateUsing(function ($state, Model $record) {
            if (! $state) {
                return null;
            }
            
            $morphMap = $this->evaluate($this->morphMap);
            $modelClass = $morphMap[$state] ?? $state;
            
            // For model classes, try to get a sensible label
            if (class_exists($modelClass)) {
                if (method_exists($modelClass, 'getMorphLabel')) {
                    return $modelClass::getMorphLabel();
                }
                
                // Fall back to a formatted class basename
                return $this->formatClassName(class_basename($modelClass));
            }
            
            // Fall back to formatting the morph alias
            return $this->formatMorphAlias($state);
        });
        
        // Set color based on the morph type
        $this->color(function ($state, Model $record) {
            if (! $state) {
                return 'gray';
            }
            
            $morphMap = $this->evaluate($this->morphMap);
            $modelClass = $morphMap[$state] ?? $state;
            
            // If the model class has a getMorphColor method, use it
            if (class_exists($modelClass) && method_exists($modelClass, 'getMorphColor')) {
                return $modelClass::getMorphColor();
            }
            
            // Use the same color mapping as MorphTypeColumn
            return MorphTypeColumn::MORPH_COLORS[$state] ?? 'gray';
        });
        
        // Set icon based on the morph type
        $this->icon(function ($state, Model $record) {
            if (! $state) {
                return null;
            }
            
            $morphMap = $this->evaluate($this->morphMap);
            $modelClass = $morphMap[$state] ?? $state;
            
            // If the model class has a getMorphIcon method, use it
            if (class_exists($modelClass) && method_exists($modelClass, 'getMorphIcon')) {
                return $modelClass::getMorphIcon();
            }
            
            // Use the same icon mapping as MorphTypeColumn
            return MorphTypeColumn::MORPH_ICONS[$state] ?? null;
        });
        
        // Make the entry clickable to view the related model if it exists
        $this->url(function (Model $record) {
            $relationName = $this->getName();
            
            // Check if the relation is loaded and has a value
            if (! $record->relationLoaded($relationName)) {
                return null;
            }
            
            $related = $record->{$relationName};
            
            if (! $related) {
                return null;
            }
            
            // Try to generate a Filament resource URL for the related model
            return $this->getResourceUrl($related);
        });
    }
    
    public function morphMap(array | \Closure $map): static
    {
        $this->morphMap = $map;
        $this->useAppMorphMap = false;
        
        return $this;
    }
    
    public function useAppMorphMap(bool $use = true): static
    {
        $this->useAppMorphMap = $use;
        
        return $this;
    }
    
    protected function formatClassName(string $className): string
    {
        // Convert RehearsalReservation -> Rehearsal Reservation
        return str($className)
            ->kebab()
            ->replace('-', ' ')
            ->title()
            ->toString();
    }
    
    protected function formatMorphAlias(string $alias): string
    {
        // Convert rehearsal_reservation -> Rehearsal Reservation
        return str($alias)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
    
    protected function getResourceUrl(Model $model): ?string
    {
        // Try to find a Filament resource for this model
        $modelClass = get_class($model);
        
        // Check member panel resources
        $memberResources = [
            \CorvMC\SpaceManagement\Models\RehearsalReservation::class => 'filament.member.resources.reservations.view',
            \App\Models\EventReservation::class => 'filament.member.resources.reservations.view',
            \CorvMC\Events\Models\Event::class => 'filament.member.resources.events.view',
            \CorvMC\Bands\Models\Band::class => 'filament.member.resources.bands.view',
            \CorvMC\Equipment\Models\Equipment::class => 'filament.member.resources.equipment.view',
            \CorvMC\Equipment\Models\EquipmentLoan::class => 'filament.member.resources.equipment-loans.view',
        ];
        
        // Check staff panel resources (for admins/staff)
        $staffResources = [
            \App\Models\User::class => 'filament.staff.resources.users.view',
            \CorvMC\Finance\Models\Charge::class => 'filament.staff.resources.charges.view',
            \CorvMC\Moderation\Models\Report::class => 'filament.staff.resources.reports.view',
            \CorvMC\Sponsorship\Models\Sponsor::class => 'filament.staff.resources.sponsors.view',
        ];
        
        if (isset($memberResources[$modelClass])) {
            try {
                return route($memberResources[$modelClass], $model);
            } catch (\Exception $e) {
                // Route doesn't exist or user doesn't have access
            }
        }
        
        if (isset($staffResources[$modelClass])) {
            try {
                return route($staffResources[$modelClass], $model);
            } catch (\Exception $e) {
                // Route doesn't exist or user doesn't have access
            }
        }
        
        return null;
    }
}