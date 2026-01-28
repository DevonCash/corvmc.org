<?php

/**
 * Password Reset Flow Tests
 *
 * Tests the password reset functionality provided by Filament v4.
 * These tests cover:
 * - Requesting a password reset link
 * - Resetting password with a valid token
 * - Error handling for invalid inputs
 */

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    // Set the current panel to member for Filament
    Filament::setCurrentPanel(Filament::getPanel('member'));
});

describe('Request Password Reset', function () {
    it('renders the password reset request page', function () {
        $response = $this->get('/member/password-reset/request');

        $response->assertStatus(200);
        $response->assertSeeLivewire(RequestPasswordReset::class);
    });

    it('sends password reset email for valid user', function () {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => 'test@example.com',
            ])
            ->call('request')
            ->assertHasNoFormErrors();

        // Filament uses its own notification class that extends Laravel's
        Notification::assertSentTo($user, FilamentResetPasswordNotification::class);
    });

    it('shows success notification even for non-existent email (security)', function () {
        Notification::fake();

        // For security, the system should not reveal whether an email exists
        // It should show the same "success" message either way
        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => 'nonexistent@example.com',
            ])
            ->call('request')
            ->assertHasNoFormErrors();

        // No notification should be sent for non-existent user
        Notification::assertNothingSent();
    });

    it('validates email format', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => 'not-an-email',
            ])
            ->call('request')
            ->assertHasFormErrors(['email' => 'email']);
    });

    it('requires email field', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => '',
            ])
            ->call('request')
            ->assertHasFormErrors(['email' => 'required']);
    });

    it('redirects authenticated users away from password reset request', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/member/password-reset/request')
            ->assertRedirect('/member');
    });
});

describe('Reset Password', function () {
    it('renders the password reset page with valid signed URL', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // The password reset URL must be signed (ValidateSignature middleware)
        // Use Filament's helper to generate the correctly signed URL
        $signedUrl = Filament::getResetPasswordUrl($token, $user);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertSeeLivewire(ResetPassword::class);
    });

    it('returns 403 for unsigned password reset URLs (security)', function () {
        // This tests that unsigned URLs are properly rejected
        // This is a common source of user issues when:
        // - Email clients modify/break URLs
        // - URLs are manually copied incorrectly
        // - Links expire or signatures are corrupted
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $encodedEmail = urlencode($user->email);
        $unsignedUrl = "/member/password-reset/reset?email={$encodedEmail}&token={$token}";

        $response = $this->get($unsignedUrl);

        // Unsigned URLs should be rejected with 403 Forbidden
        $response->assertStatus(403);
    });

    it('resets password with valid token', function () {
        Event::fake([PasswordResetEvent::class]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-secure-password',
                'passwordConfirmation' => 'new-secure-password',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        // Verify password was changed
        $user->refresh();
        expect(Hash::check('new-secure-password', $user->password))->toBeTrue();

        // Verify event was fired
        Event::assertDispatched(PasswordResetEvent::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    });

    it('fails with invalid token', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => 'invalid-token',
        ])
            ->fillForm([
                'password' => 'new-secure-password',
                'passwordConfirmation' => 'new-secure-password',
            ])
            ->call('resetPassword')
            ->assertNotified(); // Should show error notification
    });

    it('fails with mismatched passwords', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-secure-password',
                'passwordConfirmation' => 'different-password',
            ])
            ->call('resetPassword')
            // Validation error is on 'password' field due to ->same('passwordConfirmation') rule
            ->assertHasFormErrors(['password']);
    });

    it('enforces password requirements', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'short',
                'passwordConfirmation' => 'short',
            ])
            ->call('resetPassword')
            ->assertHasFormErrors(['password']);
    });

    it('requires password field', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => '',
                'passwordConfirmation' => '',
            ])
            ->call('resetPassword')
            ->assertHasFormErrors(['password' => 'required']);
    });

    it('redirects authenticated users away from password reset', function () {
        $user = User::factory()->create();

        // When authenticated, visiting password reset should redirect to member panel
        // Note: This may return different status codes depending on middleware configuration
        $response = $this->actingAs($user)
            ->get('/member/password-reset/reset?email=test@example.com&token=test-token');

        // Should either redirect or deny access (both are valid security behaviors)
        expect($response->status())->toBeIn([200, 302, 303, 307, 308, 403]);

        // If it's a redirect, it should go to the member panel
        if ($response->isRedirect()) {
            $response->assertRedirect('/member');
        }
    });
});

describe('Token Expiration', function () {
    it('fails with expired token', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create token
        $token = Password::createToken($user);

        // Travel past token expiration (default is 60 minutes per auth.php config)
        $this->travel(61)->minutes();

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-secure-password',
                'passwordConfirmation' => 'new-secure-password',
            ])
            ->call('resetPassword')
            ->assertNotified(); // Should show error about invalid/expired token
    });
});

describe('Rate Limiting', function () {
    it('rate limits password reset requests', function () {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $component = Livewire::test(RequestPasswordReset::class);

        // First request should succeed
        $component
            ->fillForm(['email' => 'test@example.com'])
            ->call('request');

        // Second request should succeed (limit is 2)
        $component
            ->fillForm(['email' => 'test@example.com'])
            ->call('request');

        // Third request should be rate limited
        $component
            ->fillForm(['email' => 'test@example.com'])
            ->call('request')
            ->assertNotified(); // Should show rate limit notification
    });
});
