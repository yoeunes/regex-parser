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
 * Base exception for all regex-related errors.
 */
class RegexException extends \Exception implements RegexParserExceptionInterface
{
    public function __construct(
        string $message,
        public readonly ?int $position = null,
        public readonly ?string $snippet = null,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
