<?php

namespace CorvMC\SpaceManagement\Settings;

use Spatie\LaravelSettings\Settings;

class UltraloqSettings extends Settings
{
    public string $client_id;

    public string $client_secret;

    public string $access_token;

    public string $refresh_token;

    public ?string $token_expires_at;

    public string $device_id;

    public string $device_name;

    public static function group(): string
    {
        return 'ultraloq';
    }

    public function isConnected(): bool
    {
        return $this->access_token !== '';
    }

    public function hasDevice(): bool
    {
        return $this->device_id !== '';
    }

    public function isConfigured(): bool
    {
        return $this->isConnected() && $this->hasDevice();
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return now()->gte($this->token_expires_at);
    }
}
