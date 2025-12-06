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
 * Represents a specific type of parsing error related to invalid syntax.
 *
 * Purpose: This exception is a specialization of `ParserException`. While `ParserException`
 * is general, this class can be used to signify errors that are explicitly about the
 * grammatical structure of the regex, such as a misplaced token or an invalid sequence.
 * It helps in distinguishing between general parsing failures and concrete syntax violations.
 *
 * @see \RegexParser\Parser
 */
final class SyntaxErrorException extends ParserException implements RegexParserExceptionInterface {}
