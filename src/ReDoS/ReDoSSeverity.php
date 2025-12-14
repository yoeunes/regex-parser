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

namespace RegexParser\ReDoS;

/**
 * @api
 */
enum ReDoSSeverity: string
{
    /**
     * No significant ReDoS risk detected.
     */
    case SAFE = 'safe';

    /**
     * Low risk.
     */
    case LOW = 'low';

    /**
     * Medium risk.
     */
    case MEDIUM = 'medium';

    /**
     * Analysis could not determine the risk.
     */
    case UNKNOWN = 'unknown';

    /**
     * High risk.
     */
    case HIGH = 'high';

    /**
     * Critical risk.
     */
    case CRITICAL = 'critical';
}
