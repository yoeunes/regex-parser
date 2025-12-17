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
 * Represents a semantic validation error in a regex pattern.
 */
final class SemanticErrorException extends ParserException implements RegexParserExceptionInterface
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly ?string $hint = null,
        ?int $position = null,
        ?string $pattern = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $position, $pattern, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }
}
