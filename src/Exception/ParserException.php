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
 * Represents an error that occurred during the parsing phase.
 *
 * Purpose: This exception is thrown by the `Parser` when it encounters a sequence
 * of tokens that violates the grammatical rules of a regular expression. This typically
 * happens after the `Lexer` has successfully tokenized the string, but the arrangement
 * of those tokens is invalid (e.g., a quantifier with no target, an unclosed group).
 *
 * @see \RegexParser\Parser
 */
class ParserException extends \Exception implements RegexParserExceptionInterface
{
    use VisualContextTrait;

    public function __construct(string $message, ?int $position = null, ?string $pattern = null, ?\Throwable $previous = null)
    {
        $this->initializeContext($position, $pattern);

        parent::__construct($message, 0, $previous);
    }

    public static function withContext(string $message, int $position, string $pattern, ?\Throwable $previous = null): self
    {
        return new self($message, $position, $pattern, $previous);
    }
}
