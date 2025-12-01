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
 * Indicates that parsing was halted because a resource limit was exceeded.
 *
 * Purpose: This exception acts as a safeguard to prevent resource exhaustion, which could
 * lead to a Denial of Service (DoS) vulnerability. It is thrown by the `Parser` if the
 * number of AST nodes generated during parsing exceeds a predefined threshold. This is
* crucial for safely handling complex or maliciously crafted regular expressions that
 * would otherwise consume excessive memory.
 *
 * @see \RegexParser\Parser
 */
class ResourceLimitException extends ParserException implements RegexParserExceptionInterface {}
