<?php

namespace App\Exceptions\Services;

use Exception;

class CrudServiceException extends Exception
{
    public static function serviceNotSpecified(string $className): self
    {
        return new self("No CRUD service specified for {$className}. Set the protected static ?\$crudService property");
    }

    public static function serviceClassNotFound(string $serviceName): self
    {
        return new self("CRUD service class or facade not found: {$serviceName}");
    }

    public static function methodNotFound(string $serviceName, string $methodName): self
    {
        return new self("Method '{$methodName}' not found in service '{$serviceName}'");
    }

    public static function serviceInstantiationFailed(string $serviceName, string $reason): self
    {
        return new self("Failed to instantiate service '{$serviceName}': {$reason}");
    }

    public static function invalidServiceConfiguration(string $serviceName, string $reason): self
    {
        return new self("Invalid service configuration for '{$serviceName}': {$reason}");
    }

    public static function operationNotSupported(string $operation, string $serviceName): self
    {
        return new self("Operation '{$operation}' is not supported by service '{$serviceName}'");
    }

    public static function bulkOperationFailed(string $operation, int $totalRecords, int $failedRecords): self
    {
        return new self("Bulk {$operation} operation failed: {$failedRecords} out of {$totalRecords} records failed to process");
    }

    public static function invalidModelType(string $expectedType, string $actualType): self
    {
        return new self("Invalid model type: expected '{$expectedType}', got '{$actualType}'");
    }
}