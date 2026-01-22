<?php

namespace CorvMC\Membership\Actions\MemberProfiles;

use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDirectoryStats
{
    use AsAction;

    /**
     * Get member directory statistics.
     */
    public function handle(): array
    {
        return [
            'total_members' => MemberProfile::count(),
            'public_profiles' => MemberProfile::where('visibility', 'public')->count(),
            'seeking_bands' => MemberProfile::withFlag('seeking_band')->count(),
            'available_for_session' => MemberProfile::withFlag('available_for_session')->count(),
            'top_skills' => $this->getTopTags('skill', 10),
            'top_genres' => $this->getTopTags('genre', 10),
        ];
    }

    /**
     * Get most popular tags by type.
     */
    protected function getTopTags(string $type, int $limit = 10): array
    {
        return DB::table('taggables')
            ->join('tags', 'tags.id', '=', 'taggables.tag_id')
            ->where('taggables.taggable_type', MemberProfile::class)
            ->where('tags.type', $type)
            ->select('tags.name', DB::raw('COUNT(*) as count'))
            ->groupBy('tags.name')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('count', 'name')
            ->toArray();
    }
}
