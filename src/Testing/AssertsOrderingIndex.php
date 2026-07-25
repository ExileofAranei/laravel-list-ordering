<?php

namespace ExileOfAranei\ListOrdering\Testing;

use ExileOfAranei\ListOrdering\Internal\IndexInspector;
use PHPUnit\Framework\TestCase;

/**
 * Mix into a consumer's own PHPUnit/Pest test case to assert, on their own
 * schedule (CI, locally), that a model's declared ordering columns still
 * match its actual unique index — the same check the
 * `list-ordering:check-index` artisan command runs, as an assertion instead.
 *
 * @phpstan-require-extends TestCase
 */
trait AssertsOrderingIndex
{
    protected function assertOrderingIndexMatches(string $modelClass): void
    {
        $problems = (new IndexInspector)->diff($modelClass);

        $this->assertSame(
            [],
            $problems,
            sprintf(
                "The declared ordering columns for %s do not match its unique index:\n- %s",
                $modelClass,
                implode("\n- ", $problems),
            ),
        );
    }
}
