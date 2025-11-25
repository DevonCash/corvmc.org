<?php

namespace App\Filament\Kiosk\Widgets;

use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.kiosk.widgets.quick-actions';

    protected int | string | array $columnSpan = 'full';
}
