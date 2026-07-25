<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use ExileOfAranei\ListOrdering\Contracts\Orderable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single global list — no grouping columns at all.
 */
class WishlistItem extends Model implements Orderable
{
    use HasOrdering;

    protected $table = 'wishlist_items';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return [];
    }
}
