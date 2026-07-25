<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use ExileOfAranei\ListOrdering\Contracts\Orderable;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately declares group columns that don't match the actual unique
 * index on shopping_list_entries (list_id, rank) — exists only to exercise
 * the index-drift tooling (ticket 07) against a genuine mismatch.
 */
class MisconfiguredEntry extends Model implements Orderable
{
    use HasOrdering;

    protected $table = 'shopping_list_entries';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return ['wrong_column'];
    }
}
