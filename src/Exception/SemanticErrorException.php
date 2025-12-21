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
final class SemanticErrorException extends RegexException implements RegexParserExceptionInterface
{
    use VisualContextTrait;

    public function __construct(
        string $message,
        ?int $position = null,
        ?string $pattern = null,
        ?\Throwable $previous = null,
        ?string $errorCode = 'regex.semantic',
    ) {
        $this->initializeContext($position, $pattern);

        parent::__construct($message, $position, $this->snippet, $errorCode, $previous);
    }
}
