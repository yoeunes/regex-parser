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

/**
 * Represents a single linter diagnostic for a regex pattern.
 */
final readonly class LintIssue
{
    public function __construct(
        public string $id,
        public string $message,
        public ?int $offset = null,
        public ?string $hint = null,
    ) {}
}
