<?php

namespace Tests\Unit\Models;

use App\Models\Invitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

describe('Invitation Model', function () {
    
    it('creates token automatically', function () {
        $invitation = Invitation::create([
            'email' => 'auto@example.com',
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->token)->not->toBeNull()
            ->and(strlen($invitation->token))->toBe(32); // 16 bytes = 32 hex chars
    });

    it('sets default expiration', function () {
        $invitation = Invitation::create([
            'email' => 'expire@example.com',
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->expires_at)->not->toBeNull()
            ->and($invitation->expires_at->isAfter(Carbon::now()))->toBeTrue()
            ->and($invitation->expires_at->isBefore(Carbon::now()->addWeeks(2)))->toBeTrue();
    });

    it('can be marked as sent and used', function () {
        $invitation = Invitation::create([
            'email' => 'mark@example.com',
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->last_sent_at)->toBeNull()
            ->and($invitation->used_at)->toBeNull()
            ->and($invitation->isUsed())->toBeFalse();

        $invitation->markAsSent();
        expect($invitation->fresh()->last_sent_at)->not->toBeNull();

        $invitation->markAsUsed();
        expect($invitation->fresh()->used_at)->not->toBeNull()
            ->and($invitation->fresh()->isUsed())->toBeTrue();
    });

    it('has proper relationships', function () {
        $inviter = User::factory()->create();
        
        $invitation = Invitation::create([
            'email' => 'relationship@example.com',
            'inviter_id' => $inviter->id,
        ]);

        expect($invitation->inviter)->toBeInstanceOf(User::class)
            ->and($invitation->inviter->id)->toBe($inviter->id);
    });

    it('validates expiration correctly', function () {
        $expiredInvitation = Invitation::create([
            'email' => 'expired@example.com',
            'expires_at' => Carbon::now()->subDay(),
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($expiredInvitation->isExpired())->toBeTrue();

        $validInvitation = Invitation::create([
            'email' => 'valid@example.com',
            'expires_at' => Carbon::now()->addDay(),
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($validInvitation->isExpired())->toBeFalse();
    });

    it('sets default message when none provided', function () {
        $invitation = Invitation::create([
            'email' => 'default@example.com',
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->message)->toBe('Join me at Corvallis Music Collective!');
    });

    it('uses provided message when specified', function () {
        $customMessage = 'Custom invitation message';
        $invitation = Invitation::create([
            'email' => 'custom@example.com',
            'message' => $customMessage,
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->message)->toBe($customMessage);
    });

    it('stores JSON data correctly', function () {
        $data = ['band_id' => 123, 'roles' => ['band leader']];
        $invitation = Invitation::create([
            'email' => 'data@example.com',
            'data' => $data,
            'inviter_id' => User::factory()->create()->id,
        ]);

        expect($invitation->data)->toBe($data);
    });
});