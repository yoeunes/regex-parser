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
 * A DTO representing the result of a validation pass.
 */
readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?string $error = null,
        public int $complexityScore = 0,
    ) {}
}
