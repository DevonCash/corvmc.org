<?php

namespace App\Filament\Resources\Equipment\Actions;

/**
 * Factory class for all Equipment-related Filament actions.
 * 
 * Provides convenient access to all equipment actions that correspond
 * to EquipmentService methods, organized by functionality.
 */
class EquipmentActions
{
    /**
     * Actions for individual equipment management.
     */
    public static function checkoutToMember()
    {
        return CheckoutToMemberAction::make();
    }
    
    public static function processReturn()
    {
        return ProcessReturnAction::make();
    }
    
    public static function markOverdue()
    {
        return MarkOverdueAction::make();
    }
    
    public static function markReturnedToOwner()
    {
        return MarkReturnedToOwnerAction::make();
    }
    
    public static function viewLoanHistory()
    {
        return ViewLoanHistoryAction::make();
    }
    
    /**
     * Actions for kit/multi-piece equipment.
     */
    public static function checkoutKitComponents()
    {
        return CheckoutKitComponentsAction::make();
    }
    
    /**
     * CRUD actions for equipment management.
     */
    public static function create()
    {
        return CreateEquipmentAction::make();
    }
    
    public static function edit()
    {
        return EditEquipmentAction::make();
    }
    
    public static function delete()
    {
        return DeleteEquipmentAction::make();
    }
    
    public static function restore()
    {
        return RestoreEquipmentAction::make();
    }
    
    public static function forceDelete()
    {
        return ForceDeleteEquipmentAction::make();
    }
    
    public static function replicate()
    {
        return ReplicateEquipmentAction::make();
    }
    
    /**
     * Bulk actions for equipment management.
     */
    public static function bulkDelete()
    {
        return BulkDeleteEquipmentAction::make();
    }
    
    /**
     * Administrative and reporting actions.
     */
    public static function viewStatistics()
    {
        return ViewStatisticsAction::make();
    }
    
    public static function export()
    {
        return ExportEquipmentAction::make();
    }
    
    /**
     * Get all standard equipment actions for use in resource pages.
     * 
     * @return array Array of action instances
     */
    public static function getStandardActions(): array
    {
        return [
            self::checkoutToMember(),
            self::checkoutKitComponents(), 
            self::processReturn(),
            self::markOverdue(),
            self::markReturnedToOwner(),
            self::viewLoanHistory(),
            self::edit(),
            self::replicate(),
            self::delete(),
        ];
    }
    
    /**
     * Get CRUD actions for resource management.
     * 
     * @return array Array of action instances
     */
    public static function getCrudActions(): array
    {
        return [
            self::edit(),
            self::replicate(),
            self::delete(),
            self::restore(),
            self::forceDelete(),
        ];
    }
    
    /**
     * Get bulk actions for table operations.
     * 
     * @return array Array of action instances
     */
    public static function getBulkActions(): array
    {
        return [
            self::bulkDelete(),
        ];
    }
    
    /**
     * Get header actions for list pages.
     * 
     * @return array Array of action instances
     */
    public static function getHeaderActions(): array
    {
        return [
            self::create(),
            self::export(),
            self::viewStatistics(),
        ];
    }
    
    /**
     * Get administrative actions for use in admin-only contexts.
     * 
     * @return array Array of action instances
     */
    public static function getAdminActions(): array
    {
        return [
            self::viewStatistics(),
            self::export(),
            self::forceDelete(),
        ];
    }
    
    /**
     * Get actions specific to kit management.
     * 
     * @return array Array of action instances  
     */
    public static function getKitActions(): array
    {
        return [
            self::checkoutKitComponents(),
        ];
    }
    
    /**
     * Get loan management actions.
     * 
     * @return array Array of action instances
     */
    public static function getLoanActions(): array
    {
        return [
            self::processReturn(),
            self::markOverdue(),
            self::viewLoanHistory(),
        ];
    }
}