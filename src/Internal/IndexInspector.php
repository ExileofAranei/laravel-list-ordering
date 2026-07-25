<?php

namespace ExileOfAranei\ListOrdering\Internal;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal Not part of the package's public contract. Backs both the
 * `list-ordering:check-index` command and Testing\AssertsOrderingIndex —
 * one comparison, two ways to run it, never two implementations of it.
 */
final class IndexInspector
{
    /**
     * Compares a model's declared ordering columns against the actual unique
     * indexes on its table. Returns a list of human-readable problems; an
     * empty list means a matching unique index was found.
     *
     * @param  class-string<Model&Orderable>  $modelClass
     * @return list<string>
     */
    public function diff(string $modelClass): array
    {
        $model = new $modelClass;

        $rankColumn = $model->orderingRankColumn();

        $expected = [...$model->orderingGroupColumns(), $rankColumn];
        sort($expected);

        $indexes = $model->getConnection()->getSchemaBuilder()->getIndexes($model->getTable());

        $candidates = array_values(array_filter(
            $indexes,
            fn (array $index) => $index['unique'] && in_array($rankColumn, $index['columns'], true),
        ));

        if ($candidates === []) {
            return [sprintf(
                'No unique index on "%s" includes the rank column "%s". Expected a unique index on [%s].',
                $model->getTable(),
                $rankColumn,
                implode(', ', $expected),
            )];
        }

        $problems = [];

        foreach ($candidates as $candidate) {
            $actual = $candidate['columns'];
            sort($actual);

            if ($actual === $expected) {
                return [];
            }

            // No exact match among the candidates: report the diff against
            // each one by name, rather than silently picking the first —
            // which candidate the DB driver returns first is an
            // implementation detail, not something the developer chose.
            $missing = array_values(array_diff($expected, $actual));
            $extra = array_values(array_diff($actual, $expected));

            if ($missing !== []) {
                $problems[] = sprintf('Missing from the index "%s": %s', $candidate['name'], implode(', ', $missing));
            }

            if ($extra !== []) {
                $problems[] = sprintf('Unexpected in the index "%s": %s', $candidate['name'], implode(', ', $extra));
            }
        }

        return $problems;
    }
}
