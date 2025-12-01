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
 * Indicates that parsing was halted due to excessive recursion depth.
 *
 * Purpose: This exception is a critical security measure to prevent stack overflow
 * errors. The `Parser` uses a recursive descent strategy, and a deeply nested regex
 * pattern (e.g., `((((...))))`) could lead to excessive function calls. This exception
 * is thrown when the recursion depth surpasses a safe limit, effectively mitigating
 * potential Denial of Service (DoS) attacks that exploit stack depth.
 *
 * @see \RegexParser\Parser
 */
class RecursionLimitException extends ParserException implements RegexParserExceptionInterface {}
