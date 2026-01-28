<?php

// TODO: Remove this workaround once Filament v4.3.1+ is released
// PR #18654 (https://github.com/filamentphp/filament/pull/18654) was merged on Dec 5, 2025
// but AFTER v4.3.0 was released. The fix will be in v4.3.1 or later.
//
// To remove this workaround:
// 1. Run: composer update filament/filament
// 2. Verify version is >= v4.3.1
// 3. Delete this file and app/Filament/Actions/ViewAction.php
// 4. In SpaceManagementTable.php, change imports back to:
//    use Filament\Actions\{Action, ViewAction};

namespace App\Filament\Shared\Actions;

use Filament\Actions\Action as BaseAction;
use Illuminate\Database\Eloquent\Model;

class Action extends BaseAction
{
    /**
     * Override getContext() to support Single Table Inheritance.
     *
     * Filament's default implementation uses strict class equality (===) which fails
     * when table records are STI children (e.g., RehearsalReservation) of the table
     * model (e.g., Reservation). This override uses is_a() for inheritance-aware checking.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        $context = [];

        $table = $this->getTable();

        if ($table) {
            $context['table'] = true;
        }

        $record = $this->getRecord();

        if ($record && (
            (! $table)
            || (! $record instanceof Model)
            || blank($table->getModel())
            || is_a($record, $table->getModel())  // Changed from === to is_a() for STI support
        ) && filled($recordKey = $this->resolveRecordKey($record))) {
            $context['recordKey'] = $recordKey;
        }

        if ($table && $this->isBulk()) {
            $context['bulk'] = true;
        }

        if (filled($schemaComponentKey = ($this->getSchemaContainer() ?? $this->getSchemaComponent())?->getKey())) {
            $context['schemaComponent'] = $schemaComponentKey;
        }

        return $context;
    }
}
