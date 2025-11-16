<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Ast;

/**
 * Defines the semantic type of a group.
 */
enum GroupType: string
{
    /** A capturing group (...). */
    case T_GROUP_CAPTURING = 'capturing';

    /** A non-capturing group (?:...). */
    case T_GROUP_NON_CAPTURING = 'non_capturing';

    /** A named capturing group (?<name>...) or (?P<name>...). */
    case T_GROUP_NAMED = 'named';

    /** A positive lookahead (?=...). */
    case T_GROUP_LOOKAHEAD_POSITIVE = 'lookahead_positive';

    /** A negative lookahead (?!...). */
    case T_GROUP_LOOKAHEAD_NEGATIVE = 'lookahead_negative';

    /** A positive lookbehind (?<=...). */
    case T_GROUP_LOOKBEHIND_POSITIVE = 'lookbehind_positive';

    /** A negative lookbehind (?<!...). */
    case T_GROUP_LOOKBEHIND_NEGATIVE = 'lookbehind_negative';

    /** Inline flags (?i:...). */
    case T_GROUP_INLINE_FLAGS = 'inline_flags';
}
