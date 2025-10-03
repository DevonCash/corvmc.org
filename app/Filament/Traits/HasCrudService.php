<?php

namespace App\Filament\Traits;

use App\Exceptions\Services\CrudServiceException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasCrudService
{
    /**
     * The service class to use for CRUD operations.
     * Must be set in the using class as: protected static ?string $crudService = 'ServiceName';
     */

    /**
     * Get the CRUD service instance.
     */
    protected function getCrudService(): object
    {
        if (!property_exists(static::class, 'crudService') || !static::$crudService) {
            throw CrudServiceException::serviceNotSpecified(static::class);
        }

        try {
            // Try as direct service class first
            $serviceClass = "\\App\\Services\\" . static::$crudService;
            if (class_exists($serviceClass)) {
                return app($serviceClass);
            }

            // Support full class names
            if (class_exists(static::$crudService)) {
                return app(static::$crudService);
            }

            // Try as facade
            $facadeClass = "\\App\\Facades\\" . static::$crudService;
            if (class_exists($facadeClass)) {
                return app($facadeClass::getFacadeAccessor());
            }

            throw CrudServiceException::serviceClassNotFound(static::$crudService);
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw CrudServiceException::serviceInstantiationFailed(static::$crudService, $e->getMessage());
        }
    }

    /**
     * Get the CRUD method name for the given operation.
     */
    protected function getCrudMethod(string $operation): string
    {
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);
        
        return $operation . $modelName;
    }

    /**
     * Handle record creation using the CRUD service.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $service = $this->getCrudService();
        $method = $this->getCrudMethod('create');

        if (!method_exists($service, $method)) {
            throw CrudServiceException::methodNotFound(get_class($service), $method);
        }

        try {
            $result = $service->$method($data);
            
            if (!$result instanceof Model) {
                throw CrudServiceException::invalidModelType(Model::class, get_class($result));
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw new CrudServiceException("Failed to create record using service: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Handle record update using the CRUD service.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = $this->getCrudService();
        $method = $this->getCrudMethod('update');

        if (!method_exists($service, $method)) {
            throw CrudServiceException::methodNotFound(get_class($service), $method);
        }

        try {
            $result = $service->$method($record, $data);
            
            if (!$result instanceof Model) {
                throw CrudServiceException::invalidModelType(Model::class, get_class($result));
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw new CrudServiceException("Failed to update record using service: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Handle record deletion using the CRUD service.
     */
    protected function handleRecordDeletion(Model $record): void
    {
        $service = $this->getCrudService();
        $method = $this->getCrudMethod('delete');

        if (!method_exists($service, $method)) {
            // Fall back to default deletion if service doesn't have delete method
            try {
                $record->delete();
            } catch (\Exception $e) {
                throw new CrudServiceException("Failed to delete record: {$e->getMessage()}", 0, $e);
            }
            return;
        }

        try {
            $service->$method($record);
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw new CrudServiceException("Failed to delete record using service: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Override this method to customize service method resolution.
     * Useful for services with non-standard naming conventions.
     */
    protected function getCustomCrudMethods(): array
    {
        return [
            // 'create' => 'customCreateMethod',
            // 'update' => 'customUpdateMethod', 
            // 'delete' => 'customDeleteMethod',
        ];
    }

    /**
     * Get CRUD method with custom override support.
     */
    protected function getCrudMethodWithOverrides(string $operation): string
    {
        $customMethods = $this->getCustomCrudMethods();
        
        if (isset($customMethods[$operation])) {
            return $customMethods[$operation];
        }

        return $this->getCrudMethod($operation);
    }

    /**
     * Alternative method resolution that checks for custom methods first.
     */
    protected function callCrudMethod(string $operation, ...$arguments)
    {
        $service = $this->getCrudService();
        $method = $this->getCrudMethodWithOverrides($operation);

        if (!method_exists($service, $method)) {
            throw CrudServiceException::methodNotFound(get_class($service), $method);
        }

        try {
            return $service->$method(...$arguments);
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw new CrudServiceException("Failed to execute {$operation} operation: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Handle bulk actions using the service if available.
     */
    protected function handleBulkAction(string $action, array $records): mixed
    {
        $service = $this->getCrudService();
        $method = 'bulk' . Str::studly($action);
        
        try {
            if (method_exists($service, $method)) {
                $recordIds = collect($records)->pluck('id')->toArray();
                return $service->$method($recordIds);
            }

            // Fall back to individual operations
            $results = [];
            $failures = [];
            
            foreach ($records as $record) {
                try {
                    $results[] = $this->callCrudMethod($action, $record);
                } catch (\Exception $e) {
                    $failures[] = "Record {$record->id}: {$e->getMessage()}";
                    logger()->error("Bulk {$action} failed for record {$record->id}: " . $e->getMessage());
                }
            }
            
            if (!empty($failures)) {
                throw CrudServiceException::bulkOperationFailed($action, count($records), count($failures));
            }
            
            return $results;
        } catch (\Exception $e) {
            if ($e instanceof CrudServiceException) {
                throw $e;
            }
            throw new CrudServiceException("Bulk {$action} operation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if the service supports a specific operation.
     */
    protected function serviceSupports(string $operation): bool
    {
        try {
            $service = $this->getCrudService();
            $method = $this->getCrudMethodWithOverrides($operation);
            return method_exists($service, $method);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service statistics if the service supports it.
     */
    protected function getServiceStats(): array
    {
        if (!$this->serviceSupports('getStats')) {
            return [];
        }

        return $this->callCrudMethod('getStats');
    }
}