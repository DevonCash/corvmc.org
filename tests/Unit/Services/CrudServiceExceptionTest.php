<?php

use App\Exceptions\Services\CrudServiceException;

describe('CrudServiceException static methods', function () {
    it('creates service not specified exception', function () {
        $exception = CrudServiceException::serviceNotSpecified('App\\Filament\\Resources\\UserResource');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('No CRUD service specified for App\\Filament\\Resources\\UserResource')
            ->and($exception->getMessage())->toContain('Set the protected static ?$crudService property');
    });

    it('creates service class not found exception', function () {
        $exception = CrudServiceException::serviceClassNotFound('NonExistentService');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('CRUD service class or facade not found: NonExistentService');
    });

    it('creates method not found exception', function () {
        $exception = CrudServiceException::methodNotFound('UserService', 'createUser');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Method \'createUser\' not found in service \'UserService\'');
    });

    it('creates service instantiation failed exception', function () {
        $exception = CrudServiceException::serviceInstantiationFailed('UserService', 'Constructor requires database connection');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Failed to instantiate service \'UserService\'')
            ->and($exception->getMessage())->toContain('Constructor requires database connection');
    });

    it('creates invalid service configuration exception', function () {
        $exception = CrudServiceException::invalidServiceConfiguration('UserService', 'Missing required config key');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Invalid service configuration for \'UserService\'')
            ->and($exception->getMessage())->toContain('Missing required config key');
    });

    it('creates operation not supported exception', function () {
        $exception = CrudServiceException::operationNotSupported('bulkDelete', 'ReadOnlyService');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Operation \'bulkDelete\' is not supported by service \'ReadOnlyService\'');
    });

    it('creates bulk operation failed exception', function () {
        $exception = CrudServiceException::bulkOperationFailed('delete', 100, 15);
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Bulk delete operation failed')
            ->and($exception->getMessage())->toContain('15 out of 100 records failed to process');
    });

    it('creates invalid model type exception', function () {
        $exception = CrudServiceException::invalidModelType('App\\Models\\User', 'stdClass');
        
        expect($exception)->toBeInstanceOf(CrudServiceException::class)
            ->and($exception->getMessage())->toContain('Invalid model type')
            ->and($exception->getMessage())->toContain('expected \'App\\Models\\User\'')
            ->and($exception->getMessage())->toContain('got \'stdClass\'');
    });
});