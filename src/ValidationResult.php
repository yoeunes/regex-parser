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
 * return the results from the `Regex::validate()` method. Instead of just returning
 * a boolean, it carries the validity status, a detailed error message if validation
 * failed, and a calculated complexity score. This gives the user rich, actionable
 * information about the pattern they are testing.
 */
readonly class ValidationResult
{
    /**
     * Creates a new, immutable ValidationResult instance.
     *
     * Purpose: This constructor initializes the result object that communicates the outcome
     * of a validation attempt. It's used by the `Regex::validate()` method to package the
     * results in a clean, type-safe way. As a contributor, you'll see this object created
     * after a regex is parsed and analyzed for both syntactic correctness and semantic issues.
     *
     * @param bool        $isValid         `true` if the regex pattern is syntactically and semantically valid,
     *                                     `false` otherwise
     * @param string|null $error           If validation fails, this contains a human-readable message explaining the
     *                                     issue (e.g., "Unclosed group"). It is `null` for valid patterns.
     * @param int         $complexityScore A numerical score representing the calculated complexity of the regex,
     *                                     derived from the `ComplexityScoreNodeVisitor`. Higher scores may indicate a
     *                                     higher risk of performance issues or ReDoS-like behavior. This is provided
     *                                     even for valid patterns.
     */
    public function __construct(
        public bool $isValid,
        public ?string $error = null,
        public int $complexityScore = 0,
    ) {}
}
