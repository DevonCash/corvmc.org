<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BylawsSettings extends Settings
{
    public string $content;

    public ?int $last_updated_by;

    public ?string $last_updated_at;

    public static function group(): string
    {
        return 'bylaws';
    }
}
