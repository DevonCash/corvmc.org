<?php

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Sponsorship\Models\Sponsor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

Route::get('/events/{event}', function (Event $event, Request $request) {
    $isPreview = $request->hasValidSignature();

    // Allow preview access via signed URL
    if (! $isPreview) {
        Gate::authorize('view', $event);
    }

    return view('events::public.show', compact('event', 'isPreview'));
})->where('event', '[0-9]+')->name('events.show');

Route::get('/show-tonight', function () {
    // Find all published events happening today
    $tonightShows = Event::publishedToday()->get();

    // If exactly one show tonight, redirect to it
    if ($tonightShows->count() === 1) {
        return redirect()->route('events.show', $tonightShows->first());
    }

    // Multiple shows tonight
    if ($tonightShows->count() > 1) {
        return redirect()->route('events.index')
            ->with('info', 'Multiple shows tonight! Check out what\'s happening below.');
    }

    // No shows tonight
    return redirect()->route('events.index')
        ->with('info', 'No shows tonight, but check out our upcoming events!');
})->name('show-tonight');

// Directory (combined musicians & bands)
Route::get('/directory', function () {
    return view('public.directory');
})->name('directory');

// Redirect old URLs to directory with appropriate tab
Route::get('/members', function () {
    return redirect()->route('directory', ['tab' => 'musicians']);
})->name('members.index');

Route::get('/bands', function () {
    return redirect()->route('directory', ['tab' => 'bands']);
})->name('bands.index');

// Individual profile pages still work
Route::get('/members/{memberProfile}', [\App\Http\Controllers\PublicMemberController::class, 'show'])->where('memberProfile', '[0-9]+')->name('members.show');

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

Route::get('/local-resources', function () {
    $lists = \App\Models\ResourceList::published()
        ->with(['publishedResources'])
        ->ordered()
        ->get();

    return view('public.local-resources', compact('lists'));
})->name('local-resources');

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

// Ticket purchase page (supports guest checkout, no auth required)
Route::get('/events/{event}/tickets', function (Event $event) {
    Gate::authorize('view', $event);

    if (!$event->hasNativeTicketing()) {
        abort(404);
    }

    return view('events::public.tickets', compact('event'));
})->where('event', '[0-9]+')->name('events.tickets');

// Ticket checkout routes (supports guest checkout, no auth required)
Route::get('/tickets/checkout/success', [\App\Http\Controllers\TicketCheckoutController::class, 'success'])
    ->name('tickets.checkout.success');
Route::get('/tickets/checkout/free-success/{order:uuid}', [\App\Http\Controllers\TicketCheckoutController::class, 'freeSuccess'])
    ->name('tickets.checkout.free-success');
Route::get('/tickets/checkout/cancel/{order:uuid}', [\App\Http\Controllers\TicketCheckoutController::class, 'cancel'])
    ->name('tickets.checkout.cancel');

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
