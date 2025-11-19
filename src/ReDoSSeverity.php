<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser;

enum ReDoSSeverity: string
{
    /**
     * No significant ReDoS risk detected.
     * Pattern uses bounded quantifiers or no recursion.
     */
    case SAFE = 'safe';

    /**
     * Low risk. Nested quantifiers exist but are strictly bounded (e.g., (a{1,5}){1,5}).
     * Linear to polynomial complexity with low constants.
     */
    case LOW = 'low';

    /**
     * Medium risk. Single unbounded quantifiers or very large bounded repetitions.
     * Potential polynomial time O(n^2).
     */
    case MEDIUM = 'medium';

    /**
     * High risk. Nested unbounded quantifiers detected.
     * Potential exponential time O(2^n).
     */
    case HIGH = 'high';

    /**
     * Critical risk. Definite catastrophic backtracking detected.
     * Patterns like (a*)* or overlapping alternations inside loops.
     */
    case CRITICAL = 'critical';
}
