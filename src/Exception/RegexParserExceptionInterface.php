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
 * Defines a common contract for all exceptions thrown by the regex-parser library.
 *
 * Purpose: This interface serves as a universal catch-all for any exception originating
 * from this library. It allows consumers to write a single `catch` block to handle all
 * potential errors from the parser, lexer, or other components, without needing to
 * catch specific exception types. This simplifies error handling for the end-user.
 *
 * @example
 * ```php
 * try {
 *     $ast = $regexService->parse('/(a|b/i');
 * } catch (RegexParserExceptionInterface $e) {
 *     // This will catch LexerException, ParserException, etc.
 *     echo "An error occurred while parsing the regex: " . $e->getMessage();
 * }
 * ```
 */
interface RegexParserExceptionInterface extends \Throwable {}
