<?php

namespace App\Actions\Curzzo;

use App\Models\Community;

class ReorderCurzzos
{
    /**
     * Assigns positions in the order of $ids (index 0 → position 0, etc).
     * IDs not belonging to the community are silently ignored.
     *
     * @param  array<int, int>  $ids
     */
    public function execute(Community $community, array $ids): void
    {
        foreach ($ids as $position => $id) {
            $community->curzzos()->where('id', $id)->update(['position' => $position]);
        }
    }
}
