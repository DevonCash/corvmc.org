<?php

namespace CorvMC\SpaceManagement\Data;

use Spatie\LaravelData\Data;

class ValidationResult extends Data
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public ?array $conflicts = null,
    ) {}
    
    public static function success(): self
    {
        return new self(valid: true);
    }
    
    public static function failure(array $errors, ?array $conflicts = null): self
    {
        return new self(
            valid: false,
            errors: $errors,
            conflicts: $conflicts
        );
    }
}