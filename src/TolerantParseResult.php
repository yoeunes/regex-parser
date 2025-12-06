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

namespace RegexParser;

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;

/**
 * Represents the outcome of a tolerant parse attempt.
 *
 * @api
 */
final readonly class TolerantParseResult
{
    /**
     * @param list<ParserException|LexerException> $errors
     */
    public function __construct(
        public \RegexParser\Node\RegexNode $ast,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
