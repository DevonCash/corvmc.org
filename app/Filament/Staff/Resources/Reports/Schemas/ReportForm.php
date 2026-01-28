<?php

namespace App\Filament\Staff\Resources\Reports\Schemas;

use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form mainly for resolving reports, not creating them
            ]);
    }
}
