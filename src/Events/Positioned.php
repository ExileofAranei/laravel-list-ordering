<?php

namespace ExileOfAranei\ListOrdering\Events;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Support\GroupKey;
use Illuminate\Database\Eloquent\Model;

final class Positioned
{
    public function __construct(
        public readonly Model&Orderable $model,
        public readonly ?GroupKey $from,
        public readonly GroupKey $to,
    ) {}
}
