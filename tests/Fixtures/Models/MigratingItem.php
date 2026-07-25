<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use ExileOfAranei\ListOrdering\Contracts\Orderable;
use Illuminate\Database\Eloquent\Model;

class MigratingItem extends Model implements Orderable
{
    use HasOrdering;

    protected $table = 'migrating_items';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return ['list_id'];
    }
}
