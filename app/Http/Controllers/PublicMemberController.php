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
        $query = QueryBuilder::for(MemberProfile::class)
            ->with(['user', 'tags'])
            ->where('visibility', 'public')
            ->allowedFilters([
                AllowedFilter::partial('name', 'user.name'),
                AllowedFilter::partial('hometown'),
                AllowedFilter::callback('skill', function ($query, $value) {
                    $query->withAnyTags([$value], 'skill');
                }),
                AllowedFilter::callback('genre', function ($query, $value) {
                    $query->withAnyTags([$value], 'genre');
                }),
                AllowedFilter::callback('flag', function ($query, $value) {
                    $query->withFlag($value);
                }),
            ])
            ->allowedSorts(['created_at', 'user.name'])
            ->defaultSort('-created_at');

        $members = $query->paginate(12)->withQueryString();

        // Get available filter options from public member profiles
        $skills = MemberProfile::where('visibility', 'public')
            ->with('tags')
            ->get()
            ->flatMap(function ($profile) {
                return $profile->tagsWithType('skill');
            })
            ->pluck('name', 'name')
            ->unique()
            ->sort();

        $genres = MemberProfile::where('visibility', 'public')
            ->with('tags')
            ->get()
            ->flatMap(function ($profile) {
                return $profile->tagsWithType('genre');
            })
            ->pluck('name', 'name')
            ->unique()
            ->sort();

        $flags = app(MemberDirectorySettings::class)->getAvailableFlags();

        $filters = [
            [
                'name' => 'filter[skill]',
                'label' => 'Skills',
                'placeholder' => 'All Skills',
                'options' => $skills->toArray(),
            ],
            [
                'name' => 'filter[genre]',
                'label' => 'Genres',
                'placeholder' => 'All Genres',
                'options' => $genres->toArray(),
            ],
            [
                'name' => 'filter[flag]',
                'label' => 'Looking For',
                'placeholder' => 'All Members',
                'options' => $flags,
            ],
        ];

        return view('public.members.index', compact('members', 'filters'));
    }

    public function show(MemberProfile $memberProfile)
    {
        abort_unless($memberProfile->isVisible(auth()->user()), 404);

        $memberProfile->load(['user', 'tags', 'user.bandProfiles', 'media']);

        return view('public.members.show', compact('memberProfile'));
    }
}
