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

namespace RegexParser\Exception;

/**
 * Thrown when resource usage (e.g., node count) exceeds the maximum allowed limit.
 * Prevents Denial of Service attacks through resource exhaustion.
 */
class ResourceLimitException extends ParserException implements RegexParserExceptionInterface {}
