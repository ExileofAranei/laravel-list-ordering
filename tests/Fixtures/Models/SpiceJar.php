<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use Illuminate\Database\Eloquent\Model;

class SpiceJar extends Model
{
    use HasOrdering;

    protected $table = 'spice_jars';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return ['pantry_id', 'shelf'];
    }
}
