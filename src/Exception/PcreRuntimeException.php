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
 * Represents a PCRE compilation/runtime error detected during validation.
 */
final class PcreRuntimeException extends ParserException implements RegexParserExceptionInterface
{
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        ?int $position = null,
        ?string $pattern = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $position, $pattern, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
