<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MemberDirectorySettings extends Settings
{
    public array $available_flags;

    public static function group(): string
    {
        return 'member_directory';
    }

    public function getAvailableFlags(): array
    {
        return $this->available_flags ?? [
            'teacher' => 'Music Teacher',
            'performer' => 'Live Performer', 
            'producer' => 'Producer/Engineer',
            'songwriter' => 'Songwriter',
            'session_musician' => 'Session Musician',
            'collaborator' => 'Open to Collaborations',
            'mentor' => 'Willing to Mentor',
            'student' => 'Looking to Learn',
        ];
    }

    public function getFlagLabel(string $flag): ?string
    {
        return $this->getAvailableFlags()[$flag] ?? null;
    }
}