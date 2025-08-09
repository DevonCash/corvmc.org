<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\BandProfile;
use App\Models\Production;
use App\Models\MemberProfile;
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
            ->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])
            ->get()
            ->sum(function ($reservation) {
                return $reservation->duration ?? 0;
            })
    ];

    return view('public.home', compact('upcomingEvents', 'stats'));
})->name('home');

Route::get('/about', function () {
    return view('public.about');
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

Route::get('/members', function () {
    return view('public.members.index');
})->name('members.index');

Route::get('/members/{memberProfile}', function (MemberProfile $memberProfile) {
    abort_unless($memberProfile->isVisible(auth()->user()), 404);

    return view('public.members.show', compact('memberProfile'));
})->name('members.show');

Route::get('/bands', function () {
    return view('public.bands.index');
})->name('bands.index');

Route::get('/bands/{bandProfile}', function (BandProfile $bandProfile) {
    return view('public.bands.show', compact('bandProfile'));
})->name('bands.show');

Route::get('/practice-space', function () {
    return view('public.practice-space');
})->name('practice-space');

Route::get('/contribute', function () {
    return view('public.contribute');
})->name('contribute');

Route::get('/support', function () {
    return view('public.support');
})->name('support');

Route::get('/volunteer', function () {
    return view('public.volunteer');
})->name('volunteer');

Route::get('/contact', function () {
    return view('public.contact');
})->name('contact');

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

Route::post('/invitation/accept/{token}', [\App\Http\Controllers\InvitationController::class, 'store'])
    ->name('invitation.accept.store')
    ->where('token', '.*');
