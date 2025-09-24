<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Band;
use App\Models\Production;
use App\Models\MemberProfile;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

// Public website routes
Route::get('/', function () {
    $upcomingEvents = Production::publishedUpcoming()
        ->limit(3)
        ->get();

    // Calculate stats from database
    $stats = [
        'active_members' => MemberProfile::whereIn('visibility', ['public', 'members'])->count(),
        'monthly_events' => Production::publishedUpcoming()
            ->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])
            ->count(),
        'practice_hours' => \App\Models\Reservation::where('status', 'confirmed')
            ->whereBetween('reserved_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get()
            ->sum(function ($reservation) {
                return $reservation->duration ?? 0;
            })
    ];

    return view('public.home', compact('upcomingEvents', 'stats'));
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
    return view('public.events.index');
})->name('events.index');

Route::get('/events/{production}', function (Production $production) {
    abort_if($production->published_at > now() || $production->published_at === null, 404);

    return view('public.events.show', compact('production'));
})->name('events.show');

Route::get('/show-tonight', function () {
    // Find next published production happening today
    $tonightShow = Production::publishedToday()->first();

    // If no show tonight, get next upcoming published show
    if (!$tonightShow) {
        $tonightShow = Production::publishedUpcoming()->first();
    }

    // If still no show found, redirect to events listing with message
    if (!$tonightShow) {
        return redirect()->route('events.index')
            ->with('info', 'No upcoming shows found. Check back soon for exciting events!');
    }

    // Redirect to the specific show page
    return redirect()->route('events.show', $tonightShow);
})->name('show-tonight');

Route::get('/members', [\App\Http\Controllers\PublicMemberController::class, 'index'])->name('members.index');
Route::get('/members/{memberProfile}', [\App\Http\Controllers\PublicMemberController::class, 'show'])->name('members.show');

Route::get('/bands', function () {
    return view('public.bands.index');
})->name('bands.index');

Route::get('/bands/{band}', function (Band $band) {
    abort_unless($band->isVisible(Auth::user()), 404);

    $band->load(['members', 'tags', 'media']);

    return view('public.bands.show', compact('band'));
})->name('bands.show');

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

// Equipment Library routes (public gear catalog)
Route::get('/equipment', [\App\Http\Controllers\PublicEquipmentController::class, 'index'])->name('equipment.index');
Route::get('/equipment/{equipment}', [\App\Http\Controllers\PublicEquipmentController::class, 'show'])->name('equipment.show');

Route::get('/privacy-policy', function () {
    return view('public.privacy-policy');
})->name('privacy-policy');

Route::post('/contact', function () {
    $validated = request()->validate([
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:20'],
        'subject' => ['required', 'string', 'in:general,membership,practice_space,performance,volunteer,donation'],
        'message' => ['required', 'string', 'max:2000']
    ]);

    // Log the contact submission
    logger('Contact form submission', $validated);

    // Send email notification to organization contact email
    $organizationEmail = app(\App\Settings\OrganizationSettings::class)->email;
    $staffEmail = $organizationEmail ?? config('mail.from.address');
    \Illuminate\Support\Facades\Notification::route('mail', $staffEmail)
        ->notify(new \App\Notifications\ContactFormSubmissionNotification($validated));

    return back()->with('success', 'Thank you for your message! We\'ll get back to you soon.');
})->name('contact.store');

// User invitation routes (public, no auth required)
Route::get('/invitation/accept/{token}', [\App\Http\Controllers\InvitationController::class, 'show'])
    ->name('invitation.accept')
    ->where('token', '.*'); // Allow any characters in token

// Stripe webhook (no authentication needed - Stripe validates with signature)
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');

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
            'email' => 'john@example.com'
        ]);

        $notification = new \App\Notifications\PasswordResetNotification('sample-token-12345');

        return $notification->toMail($user)->render();
    })->name('email.preview.password-reset');
}
