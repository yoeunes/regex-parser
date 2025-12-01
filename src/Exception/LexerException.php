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
 * Represents an error that occurred during the lexical analysis phase.
 *
 * Purpose: This exception is thrown by the `Lexer` when it encounters a sequence
 * of characters that it cannot tokenize. This indicates a fundamental syntax error
 * in the regular expression pattern itself, such as an invalid character sequence
 * or an unterminated construct that the lexer can detect.
 *
 * @see \RegexParser\Lexer
 */
class LexerException extends \Exception implements RegexParserExceptionInterface {}
