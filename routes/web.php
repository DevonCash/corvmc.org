<?php

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Sponsorship\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public website routes
Route::get('/', function () {
    $upcomingEvents = Event::publishedUpcoming()
        ->with('media')
        ->limit(3)
        ->get();

    // Calculate stats from database
    $stats = [
        'active_members' => MemberProfile::whereIn('visibility', ['public', 'members'])->count(),
        'monthly_events' => Event::publishedUpcoming()
            ->whereBetween('start_datetime', [now()->startOfMonth(), now()->endOfMonth()])
            ->count(),
        'practice_hours' => \CorvMC\SpaceManagement\Models\Reservation::status(ReservationStatus::Confirmed)
            ->whereBetween('reserved_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get()
            ->sum(function ($reservation) {
                return $reservation->duration ?? 0;
            }),
    ];

    // Get major sponsors for display
    $majorSponsors = Sponsor::major()->get();

    return view('public.home', compact('upcomingEvents', 'stats', 'majorSponsors'));
})->name('home');

Route::get('/about', function () {
    $boardMembers = \App\Models\StaffProfile::active()
        ->board()
        ->ordered()
        ->get();

    $staffMembers = \App\Models\StaffProfile::active()
        ->staff()
        ->ordered()
        ->get();

    // For each staff member, try to find a matching user with a public profile
    $boardMembers->each(function ($member) {
        $user = User::where('email', $member->email)->first();
        $member->user = $user;
    });

    $staffMembers->each(function ($member) {
        $user = User::where('email', $member->email)->first();
        $member->user = $user;
    });

    return view('public.about', compact('boardMembers', 'staffMembers'));
})->name('about');

Route::get('/events', function () {
    return view('events::public.index');
})->name('events.index');

Route::get('/events/{event}', function (Event $event) {
    abort_if($event->published_at > now() || $event->published_at === null, 404);

    return view('events::public.show', compact('event'));
})->where('event', '[0-9]+')->name('events.show');

Route::get('/show-tonight', function () {
    // Find next published event happening today
    $tonightShow = Event::publishedToday()->first();

    // If no show tonight, get next upcoming published show
    if (! $tonightShow) {
        $tonightShow = Event::publishedUpcoming()->first();
    }

    // If still no show found, redirect to events listing with message
    if (! $tonightShow) {
        return redirect()->route('events.index')
            ->with('info', 'No upcoming shows found. Check back soon for exciting events!');
    }

    // Redirect to the specific show page
    return redirect()->route('events.show', $tonightShow);
})->name('show-tonight');

Route::get('/members', [\App\Http\Controllers\PublicMemberController::class, 'index'])->name('members.index');
Route::get('/members/{memberProfile}', [\App\Http\Controllers\PublicMemberController::class, 'show'])->where('memberProfile', '[0-9]+')->name('members.show');

Route::get('/bands', function () {
    return view('bands::public.index');
})->name('bands.index');

Route::get('/bands/{band}', function (Band $band) {
    abort_unless($band->isVisible(Auth::user()), 404);

    $band->load(['members', 'tags', 'media']);

    return view('bands::public.show', compact('band'));
})->where('band', '[a-z0-9\-]+')->name('bands.show');

Route::get('/programs', function () {
    return view('public.programs');
})->name('programs');

Route::get('/contribute', function () {
    return view('public.contribute');
})->name('contribute');

Route::get('/support', function () {
    return redirect()->route('contribute')->with('info', 'Support options have been integrated into our contribute page.');
})->name('support');

Route::get('/volunteer', function () {
    return view('public.volunteer');
})->name('volunteer');

Route::get('/contact', function () {
    return view('public.contact');
})->name('contact');

Route::get('/sponsors', function () {
    $sponsors = Sponsor::active()
        ->groupBy('tier')
        ->ordered()
        ->get();

    return view('public.sponsors', compact('sponsors'));
})->name('sponsors');

Route::get('/about/bylaws', function () {
    $bylaws = app(\App\Settings\BylawsSettings::class);

    return view('public.bylaws', compact('bylaws'));
})->name('bylaws');

// Equipment Library routes (public gear catalog)
Route::get('/equipment', [\CorvMC\Equipment\Http\Controllers\PublicEquipmentController::class, 'index'])->name('equipment.index');
Route::get('/equipment/{equipment}', [\CorvMC\Equipment\Http\Controllers\PublicEquipmentController::class, 'show'])->where('equipment', '[0-9]+')->name('equipment.show');

Route::get('/privacy-policy', function () {
    return view('public.privacy-policy');
})->name('privacy-policy');

// User invitation routes (public, no auth required)
Route::get('/invitation/accept/{token}', [\App\Http\Controllers\InvitationController::class, 'show'])
    ->name('invitation.accept')
    ->where('token', '.*'); // Allow any characters in token

// Stripe webhook (no authentication needed - Stripe validates with signature)
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// Checkout success/cancel handling (unified for all checkout types)
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout/success', [\App\Http\Controllers\CheckoutController::class, 'success'])
        ->name('checkout.success');
    Route::get('/checkout/cancel', [\App\Http\Controllers\CheckoutController::class, 'cancel'])
        ->name('checkout.cancel');
});

// Email template preview (development only)
if (app()->environment('local', 'development')) {
    Route::get('/email-preview/password-reset', function () {
        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $notification = new \App\Notifications\PasswordResetNotification('sample-token-12345');

        return $notification->toMail($user)->render();
    })->name('email.preview.password-reset');
}
