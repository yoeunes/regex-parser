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
 * Encapsulates the outcome of a regex validation check.
 *
 * Purpose: This immutable Data Transfer Object (DTO) provides a structured way to
 * return the results from the {@see Regex::validate()} method. Instead of just
 * returning a boolean, it carries the validity status, a detailed error message
 * if validation failed, and a calculated complexity score. This gives the user
 * rich, actionable information about the pattern they are testing.
 *
 * @api
 */
readonly class ValidationResult
{
    /**
     * @param bool        $isValid         indicates whether validation succeeded (`true`) or
     *                                     `false` otherwise
     * @param string|null $error           If validation fails, this contains a human-readable
     *                                     message explaining the issue (e.g., "Unclosed group").
     *                                     It is `null` for valid patterns.
     * @param int         $complexityScore A numerical score representing the calculated
     *                                     complexity of the regex, derived from the
     *                                     `ComplexityScoreNodeVisitor`. Higher scores may
     *                                     indicate a higher risk of performance issues or
     *                                     ReDoS-like behavior. This is provided even for
     *                                     valid patterns.
     */
    public function __construct(
        public bool $isValid,
        public ?string $error = null,
        public int $complexityScore = 0,
    ) {}

    /**
     * Convenience accessor that mirrors the public `$isValid` property.
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Convenience accessor that mirrors the public `$error` property.
     */
    public function getErrorMessage(): ?string
    {
        return $this->error;
    }

    /**
     * Convenience accessor that mirrors the public `$complexityScore` property.
     */
    public function getComplexityScore(): int
    {
        return $this->complexityScore;
    }
}
