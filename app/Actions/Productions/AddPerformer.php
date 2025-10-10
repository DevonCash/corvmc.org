<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class AddPerformer
{
    use AsAction;

    /**
     * Add a performer (band) to a production.
     */
    public function handle(Production $production, Band $band, array $options = []): bool
    {
        if (HasPerformer::run($production, $band)) {
            return false;
        }

        // If no order specified, put them at the end
        if (!isset($options['order'])) {
            $options['order'] = $production->performers()->max('production_bands.order') + 1 ?? 1;
        }

        $production->performers()->attach($band->id, [
            'order' => $options['order'],
            'set_length' => $options['set_length'] ?? null,
        ]);

        return true;
    }
}
