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
 * Outcome of a regex validation check.
 */
final readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public ?string $error = null,
        public int $complexityScore = 0,
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error;
    }

    public function getComplexityScore(): int
    {
        return $this->complexityScore;
    }
}
