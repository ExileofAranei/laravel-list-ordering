<?php

namespace ExileOfAranei\ListOrdering\Support;

use Illuminate\Database\Eloquent\Builder;

final class GroupKey
{
    /** @param array<string, mixed> $columns */
    private function __construct(private readonly array $columns) {}

    /** @param array<string, mixed> $columns */
    public static function of(array $columns): self
    {
        return new self($columns);
    }

    public function applyTo(Builder $query): Builder
    {
        foreach ($this->columns as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    public function equals(self $other): bool
    {
        $mine = $this->columns;
        $theirs = $other->columns;

        ksort($mine);
        ksort($theirs);

        return $mine === $theirs;
    }

    /** @return list<string> */
    public function columnNames(): array
    {
        return array_keys($this->columns);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->columns;
    }
}
