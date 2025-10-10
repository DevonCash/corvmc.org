<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class DuplicateProduction
{
    use AsAction;

    /**
     * Duplicate a production with new date/time.
     */
    public function handle(
        Production $originalProduction,
        \DateTime $newStartTime,
        ?\DateTime $newEndTime = null,
        ?\DateTime $newDoorsTime = null
    ): Production {
        $newProduction = $originalProduction->replicate();
        $newProduction->start_time = $newStartTime;
        $newProduction->end_time = $newEndTime;
        $newProduction->doors_time = $newDoorsTime;
        $newProduction->status = 'pre-production';
        $newProduction->published_at = null;
        $newProduction->save();

        // Copy performers
        foreach ($originalProduction->performers as $performer) {
            $newProduction->performers()->attach($performer->id, [
                'order' => $performer->pivot->order,
                'set_length' => $performer->pivot->set_length,
            ]);
        }

        // Copy tags
        foreach ($originalProduction->tags as $tag) {
            $newProduction->attachTag($tag->name, $tag->type);
        }

        return $newProduction;
    }
}
