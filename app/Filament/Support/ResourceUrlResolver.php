<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class ResourceUrlResolver
{
    /**
     * Map of model classes to their resources in different panels.
     */
    protected static array $resourceMap = [
        'member' => [
            \CorvMC\SpaceManagement\Models\RehearsalReservation::class => \App\Filament\Member\Resources\Reservations\ReservationResource::class,
            \CorvMC\SpaceManagement\Models\Reservation::class => \App\Filament\Member\Resources\Reservations\ReservationResource::class,
            \App\Models\EventReservation::class => \App\Filament\Member\Resources\Reservations\ReservationResource::class,
            \CorvMC\Events\Models\Event::class => \App\Filament\Member\Resources\Events\EventResource::class,
            \CorvMC\Bands\Models\Band::class => \App\Filament\Member\Resources\Bands\BandResource::class,
            \CorvMC\Equipment\Models\Equipment::class => \App\Filament\Member\Resources\Equipment\EquipmentResource::class,
            \CorvMC\Equipment\Models\EquipmentLoan::class => \App\Filament\Member\Resources\EquipmentLoans\EquipmentLoanResource::class,
        ],
        'staff' => [
            \App\Models\User::class => \App\Filament\Staff\Resources\Users\UserResource::class,
            \CorvMC\Finance\Models\Order::class => \App\Filament\Staff\Resources\Orders\OrderResource::class,
            \CorvMC\Moderation\Models\Report::class => \App\Filament\Staff\Resources\Reports\ReportResource::class,
            \CorvMC\Moderation\Models\Revision::class => \App\Filament\Staff\Resources\Revisions\RevisionResource::class,
            \CorvMC\Sponsorship\Models\Sponsor::class => \App\Filament\Staff\Resources\Sponsors\SponsorResource::class,
            \CorvMC\Events\Models\Event::class => \App\Filament\Staff\Resources\Events\EventResource::class,
            \CorvMC\Events\Models\Venue::class => \App\Filament\Staff\Resources\Venues\VenueResource::class,
            \CorvMC\SpaceManagement\Models\SpaceClosure::class => \App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource::class,
            \CorvMC\SpaceManagement\Models\RehearsalReservation::class => \App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource::class,
            \CorvMC\SpaceManagement\Models\Reservation::class => \App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource::class,
            \App\Models\EventReservation::class => \App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource::class,
        ],
    ];
    
    /**
     * Get the URL to view a record in the appropriate panel.
     *
     * @param Model $record The model instance to get a URL for
     * @param string|null $panelId Specific panel to use, or null to use current panel
     * @param string $action The action (view, edit, etc.) - defaults to 'view'
     * @return string|null The URL or null if no resource found
     */
    public static function getUrl(Model $record, ?string $panelId = null, string $action = 'view'): ?string
    {
        // Get the current panel if not specified
        if ($panelId === null) {
            try {
                $panelId = Filament::getCurrentPanel()?->getId();
            } catch (\Exception $e) {
                // If we can't get current panel, try member then staff
                $panelId = 'member';
            }
        }
        
        // Get the model class
        $modelClass = get_class($record);
        
        // Try the specified panel first
        if (isset(static::$resourceMap[$panelId][$modelClass])) {
            $resourceClass = static::$resourceMap[$panelId][$modelClass];
            
            try {
                // Check if user can view this resource
                if (method_exists($resourceClass, 'canView') && ! $resourceClass::canView($record)) {
                    // Try falling back to another panel
                    return static::tryOtherPanels($record, $panelId, $action);
                }
                
                return $resourceClass::getUrl($action, ['record' => $record]);
            } catch (\Exception $e) {
                // If route doesn't exist or other error, try other panels
                return static::tryOtherPanels($record, $panelId, $action);
            }
        }
        
        // Try other panels if not found in specified panel
        return static::tryOtherPanels($record, $panelId, $action);
    }
    
    /**
     * Try to find a URL in other panels.
     */
    protected static function tryOtherPanels(Model $record, ?string $excludePanelId, string $action): ?string
    {
        $modelClass = get_class($record);
        $panels = ['member', 'staff'];
        
        foreach ($panels as $panelId) {
            if ($panelId === $excludePanelId) {
                continue;
            }
            
            if (isset(static::$resourceMap[$panelId][$modelClass])) {
                $resourceClass = static::$resourceMap[$panelId][$modelClass];
                
                try {
                    // Check if user can view this resource
                    if (method_exists($resourceClass, 'canView') && ! $resourceClass::canView($record)) {
                        continue;
                    }
                    
                    return $resourceClass::getUrl($action, ['record' => $record]);
                } catch (\Exception $e) {
                    // Continue to next panel
                    continue;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Register a resource for a model in a specific panel.
     */
    public static function register(string $panelId, string $modelClass, string $resourceClass): void
    {
        static::$resourceMap[$panelId][$modelClass] = $resourceClass;
    }
    
    /**
     * Get the resource class for a model in a specific panel.
     */
    public static function getResourceClass(Model $record, ?string $panelId = null): ?string
    {
        if ($panelId === null) {
            try {
                $panelId = Filament::getCurrentPanel()?->getId();
            } catch (\Exception $e) {
                $panelId = 'member';
            }
        }
        
        $modelClass = get_class($record);
        
        return static::$resourceMap[$panelId][$modelClass] ?? null;
    }
}