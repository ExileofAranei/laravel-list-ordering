<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use ExileOfAranei\ListOrdering\Contracts\Orderable;
use Illuminate\Database\Eloquent\Model;

/**
 * Its single group column, notebook_id, is nullable — notes not filed into
 * any notebook sit in the null group.
 */
class Note extends Model implements Orderable
{
    use HasOrdering;

    protected $table = 'notes';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return ['notebook_id'];
    }
}
