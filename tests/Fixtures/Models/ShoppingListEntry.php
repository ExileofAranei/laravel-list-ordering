<?php

namespace ExileOfAranei\ListOrdering\Tests\Fixtures\Models;

use ExileOfAranei\ListOrdering\Concerns\HasOrdering;
use Illuminate\Database\Eloquent\Model;

class ShoppingListEntry extends Model
{
    use HasOrdering;

    protected $table = 'shopping_list_entries';

    protected $guarded = [];

    /** @return list<string> */
    public function orderingGroupColumns(): array
    {
        return ['list_id'];
    }
}
