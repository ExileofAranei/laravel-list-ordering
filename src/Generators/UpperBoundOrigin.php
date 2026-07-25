<?php

namespace ExileOfAranei\ListOrdering\Generators;

/**
 * Where the upper bound stands relative to the digit position currently
 * being decided, inside FractionalRankGenerator::between().
 */
enum UpperBoundOrigin
{
    /** No upper bound was given at all. */
    case Absent;

    /** An upper bound was given and still constrains this position. */
    case Present;

    /** An upper bound was given but a prior digit already sorts below it. */
    case Exhausted;
}
