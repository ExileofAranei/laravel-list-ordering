<?php

namespace ExileOfAranei\ListOrdering\Generators;

use ExileOfAranei\ListOrdering\Contracts\RankGenerator;

/**
 * Ranks are treated as digits after an implicit decimal point, in a base-62
 * alphabet whose codepoint order matches its digit-value order — so byte
 * comparison of two ranks (in PHP or in a byte-collated DB column) always
 * agrees with their digit-value comparison, at any length.
 */
final class FractionalRankGenerator implements RankGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const MAX_DIGIT = 61; // index of 'z'

    public function between(?string $lower, ?string $upper): string
    {
        if ($lower !== null && $upper !== null && $lower >= $upper) {
            throw new \InvalidArgumentException('The lower bound must sort strictly before the upper bound.');
        }

        $lowerDigits = $lower !== null ? $this->digits($lower) : [];
        $upperDigits = $upper !== null ? $this->digits($upper) : null;
        $lowerBoundless = $lower === null;
        $upperState = $upperDigits === null ? UpperBoundOrigin::Absent : UpperBoundOrigin::Present;

        $result = '';
        $position = 0;

        while (true) {
            $lowerDigit = $lowerDigits[$position] ?? 0;

            if ($upperState !== UpperBoundOrigin::Present) {
                if ($lowerDigit < self::MAX_DIGIT) {
                    // Whichever side is truly unconstrained (across repeated calls, not
                    // just this one) should get the digit that preserves the most room
                    // on *its* side for future calls of the same shape, rather than
                    // splitting the remaining range in half.
                    $mid = match (true) {
                        // Both bounds were absent from the start (e.g. an empty list):
                        // no repeated-call direction is implied either way, so land
                        // near the middle instead of biasing toward either extreme.
                        $lowerBoundless && $upperState === UpperBoundOrigin::Absent => $lowerDigit + intdiv(self::MAX_DIGIT - $lowerDigit + 1, 2),
                        // A real upper bound was exhausted via forced carries (a
                        // genuine prepend-at-the-front sequence): bias toward
                        // MAX_DIGIT so this position leaves maximal room *below*
                        // it for further prepends.
                        $lowerBoundless => self::MAX_DIGIT,
                        // A real lower bound exists (append, or upper was exhausted
                        // via forced carries on an otherwise bounded insert): the
                        // smallest valid step lets this position absorb the full
                        // alphabet range before a rank needs another character.
                        default => $lowerDigit + 1,
                    };

                    return $result.self::ALPHABET[$mid];
                }

                $result .= self::ALPHABET[$lowerDigit];
                $position++;

                continue;
            }

            $upperDigit = $upperDigits[$position] ?? 0;

            if ($upperDigit - $lowerDigit >= 2) {
                // Mirrors the unbounded-above case: a genuinely absent lower bound
                // (repeated prepends at the very front) should hug just below the
                // upper digit, leaving the full range below free for further
                // prepends, rather than giving away half of it to a midpoint split.
                $mid = $lowerBoundless
                    ? $upperDigit - 1
                    : $lowerDigit + intdiv($upperDigit - $lowerDigit, 2);

                return $result.self::ALPHABET[$mid];
            }

            if ($upperDigit === $lowerDigit) {
                $result .= self::ALPHABET[$lowerDigit];
                $position++;

                continue;
            }

            // $upperDigit === $lowerDigit + 1: no room at this position. Taking the
            // lower digit already makes the result sort below $upper from here on,
            // so the upper bound stops constraining subsequent positions.
            $result .= self::ALPHABET[$lowerDigit];
            $position++;
            $upperState = UpperBoundOrigin::Exhausted;
        }
    }

    /** @return list<int> */
    private function digits(string $value): array
    {
        $digits = [];

        foreach (str_split($value) as $char) {
            $digits[] = strpos(self::ALPHABET, $char);
        }

        return $digits;
    }
}
