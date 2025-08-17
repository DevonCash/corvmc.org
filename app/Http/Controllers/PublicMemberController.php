<?php

namespace App\Http\Controllers;

use App\Models\MemberProfile;
use App\Settings\MemberDirectorySettings;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PublicMemberController extends Controller
{
    public function index(Request $request)
    {
        return view('public.members.index');
    }

    public function show(MemberProfile $memberProfile)
    {
        abort_unless($memberProfile->isVisible(auth()->user()), 404);

        $memberProfile->load(['user', 'tags', 'user.bandProfiles', 'media']);

        return view('public.members.show', compact('memberProfile'));
    }
}
