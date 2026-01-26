<?php

namespace App\Http\Controllers;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicMemberController extends Controller
{
    public function index(Request $request)
    {
        return view('public.members.index');
    }

    public function show(MemberProfile $memberProfile)
    {
        abort_unless($memberProfile->isVisible(Auth::user()), 404);

        $memberProfile->load(['user', 'tags', 'user.bands', 'media']);

        return view('public.members.show', compact('memberProfile'));
    }
}
