<?php

// TODO: Remove this workaround once Filament v4.3.1+ is released
// See app/Filament/Actions/Action.php for full details.

namespace App\Filament\Actions;

use Filament\Actions\ViewAction as BaseViewAction;
use Illuminate\Database\Eloquent\Model;

class ViewAction extends BaseViewAction
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
