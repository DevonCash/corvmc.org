<?php

namespace App\Filament\Tables\Columns;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class MorphTypeColumn extends TextColumn
{
    public const MORPH_COLORS = [
        // Reservations
        'reservation' => 'primary',
        'rehearsal_reservation' => 'primary',
        'event_reservation' => 'warning',
        
        // Events
        'event' => 'warning',
        'venue' => 'info',
        'ticket_order' => 'success',
        'ticket' => 'success',
        
        // Equipment
        'equipment' => 'gray',
        'equipment_loan' => 'indigo',
        'equipment_damage_report' => 'danger',
        
        // Finance
        'subscription' => 'purple',
        
        // Users & Profiles
        'user' => 'info',
        'member_profile' => 'info',
        'staff_profile' => 'cyan',
        
        // Bands
        'band' => 'purple',
        'band_member' => 'purple',
        
        // Moderation
        'report' => 'danger',
        'revision' => 'gray',
        
        // Others
        'sponsor' => 'success',
        'invitation' => 'gray',
        'recurring_series' => 'indigo',
    ];
    
    public const MORPH_ICONS = [
        // Reservations
        'reservation' => 'tabler-calendar-time',
        'rehearsal_reservation' => 'tabler-metronome',
        'event_reservation' => 'tabler-calendar-event',
        
        // Events
        'event' => 'tabler-calendar-event',
        'venue' => 'tabler-map-pin',
        'ticket_order' => 'tabler-ticket',
        'ticket' => 'tabler-ticket',
        
        // Equipment
        'equipment' => 'tabler-guitar-pick',
        'equipment_loan' => 'tabler-package-export',
        'equipment_damage_report' => 'tabler-alert-triangle',
        
        // Finance
        'subscription' => 'tabler-credit-card',
        
        // Users & Profiles
        'user' => 'tabler-user',
        'member_profile' => 'tabler-user-circle',
        'staff_profile' => 'tabler-user-shield',
        
        // Bands
        'band' => 'tabler-users-group',
        'band_member' => 'tabler-user',
        
        // Moderation
        'report' => 'tabler-flag',
        'revision' => 'tabler-history',
        
        // Others
        'sponsor' => 'tabler-heart-handshake',
        'invitation' => 'tabler-mail',
        'recurring_series' => 'tabler-repeat',
    ];
    
    protected array | \Closure $morphMap = [];
    
    protected bool $useAppMorphMap = true;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // The column name is the relation name (e.g., 'chargeable')
        // The actual database column is relation_name + '_type' (e.g., 'chargeable_type')
        $this->getStateUsing(function (Model $record) {
            $relationName = $this->getName();
            $typeColumn = $relationName . '_type';
            
            return $record->{$typeColumn} ?? null;
        });
        
        // Format the morph type as a badge by default
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
            
            // If the model class implements HasLabel, use its label
            if (class_exists($modelClass)) {
                $reflection = new \ReflectionClass($modelClass);
                
                // Check if it's an enum that implements HasLabel
                if ($reflection->isEnum() && $reflection->implementsInterface(HasLabel::class)) {
                    return $modelClass::from($state)->getLabel();
                }
                
                // For model classes, try to get a sensible label
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
            
            // If the model class implements HasColor, use its color
            if (class_exists($modelClass)) {
                $reflection = new \ReflectionClass($modelClass);
                
                if ($reflection->isEnum() && $reflection->implementsInterface(HasColor::class)) {
                    return $modelClass::from($state)->getColor();
                }
                
                if (method_exists($modelClass, 'getMorphColor')) {
                    return $modelClass::getMorphColor();
                }
            }
            
            // Use a consistent color based on the morph type
            return static::MORPH_COLORS[$state] ?? 'gray';
        });
        
        // Set icon based on the morph type
        $this->icon(function ($state, Model $record) {
            if (! $state) {
                return null;
            }
            
            $morphMap = $this->evaluate($this->morphMap);
            $modelClass = $morphMap[$state] ?? $state;
            
            // If the model class implements HasIcon, use its icon
            if (class_exists($modelClass)) {
                $reflection = new \ReflectionClass($modelClass);
                
                if ($reflection->isEnum() && $reflection->implementsInterface(HasIcon::class)) {
                    return $modelClass::from($state)->getIcon();
                }
                
                if (method_exists($modelClass, 'getMorphIcon')) {
                    return $modelClass::getMorphIcon();
                }
            }
            
            // Use a default icon based on the morph type
            return static::MORPH_ICONS[$state] ?? null;
        });
        
        // Make the column clickable to view the related model if it exists
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