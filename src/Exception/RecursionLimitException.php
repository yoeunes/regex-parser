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
 * Thrown when recursion depth exceeds the maximum allowed limit.
 * Prevents stack overflow attacks on deeply nested patterns.
 */
class RecursionLimitException extends ParserException implements RegexParserExceptionInterface {}
