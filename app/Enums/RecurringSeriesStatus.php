<?php

namespace App\Enums;

enum RecurringSeriesStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';
}
