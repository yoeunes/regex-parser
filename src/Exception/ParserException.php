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
 * @see \RegexParser\Parser
 *
 * @phpstan-consistent-constructor
 */
class ParserException extends RegexException implements RegexParserExceptionInterface
{
    use VisualContextTrait;

    public function __construct(string $message, ?int $position = null, ?string $pattern = null, ?\Throwable $previous = null)
    {
        $this->initializeContext($position, $pattern);

        parent::__construct($message, $position, $this->getVisualSnippet(), 'parser.error', $previous);
    }

    public static function withContext(string $message, int $position, string $pattern, ?\Throwable $previous = null): static
    {
        return new static($message, $position, $pattern, $previous);
    }
}
