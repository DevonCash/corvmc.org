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
    $upcomingEvents = Production::where('published_at', '<=', now())
        ->where('start_time', '>', now())
        ->orderBy('start_time')
        ->limit(3)
        ->get();

    return view('public.home', compact('upcomingEvents'));
})->name('home');

Route::get('/about', function () {
    return view('public.about');
})->name('about');

Route::get('/events', function () {
    $events = Production::where('published_at', '<=', now())
        ->where('start_time', '>', now())
        ->orderBy('start_time')
        ->paginate(12);

    return view('public.events.index', compact('events'));
})->name('events.index');

Route::get('/events/{production}', function (Production $production) {
    abort_if($production->published_at > now() || $production->published_at === null, 404);

    return view('public.events.show', compact('production'));
})->name('events.show');

Route::get('/show-tonight', function () {
    // Find next published production happening today or next upcoming show
    $tonightShow = Production::where('published_at', '<=', now())
        ->where('start_time', '>=', now()->startOfDay())
        ->where('start_time', '<=', now()->endOfDay())
        ->orderBy('start_time')
        ->first();

    // If no show tonight, get next upcoming published show
    if (!$tonightShow) {
        $tonightShow = Production::where('published_at', '<=', now())
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->first();
    }

    // If still no show found, redirect to events listing
    if (!$tonightShow) {
        return redirect()->route('events.index')
            ->with('info', 'No upcoming shows found. Check back soon!');
    }

    return redirect()->route('events.show', $tonightShow);
})->name('show-tonight');

Route::get('/members', function () {
    $members = QueryBuilder::for(MemberProfile::class)
        ->allowedFilters(['name', 'hometown', AllowedFilter::scope('withAllTags')])
        ->with('user')
        ->whereIn('visibility', ['public'])
        ->paginate(24);

    return view('public.members.index', compact('members'));
})->name('members.index');

Route::get('/members/{memberProfile}', function (MemberProfile $memberProfile) {
    abort_unless($memberProfile->isVisible(auth()->user()), 404);

    return view('public.members.show', compact('memberProfile'));
})->name('members.show');

Route::get('/bands', function () {
    $bands = BandProfile::with('members')
        ->whereIn('visibility', ['public', 'members'])
        ->paginate(24);

    return view('public.bands.index', compact('bands'));
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
    // Handle contact form submission
    return back()->with('success', 'Thank you for your message! We\'ll get back to you soon.');
})->name('contact.store');
