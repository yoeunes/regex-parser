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

namespace RegexParser\Bridge\Symfony\Service;

/**
 * Output from a lint run.
 *
 * @internal
 *
 * @phpstan-type LintIssue array{
 *     type: string,
 *     message: string,
 *     file: string,
 *     line: int,
 *     column?: int,
 *     issueId?: string,
 *     hint?: string|null,
 *     source?: string,
 *     pattern?: string,
 *     regex?: string
 * }
 * @phpstan-type OptimizationEntry array{
 *     file: string,
 *     line: int,
 *     optimization: \RegexParser\OptimizationResult,
 *     savings: int,
 *     source?: string
 * }
 * @phpstan-type LintResult array{
 *     file: string,
 *     line: int,
 *     source?: string|null,
 *     pattern: string|null,
 *     location?: string|null,
 *     issues: list<LintIssue>,
 *     optimizations: list<OptimizationEntry>
 * }
 * @phpstan-type LintStats array{errors: int, warnings: int, optimizations: int}
 */
final readonly class RegexLintReport
{
    /**
     * @param list<LintResult> $results
     * @param LintStats $stats
     */
    public function __construct(
        /** @var list<LintResult> */
        public array $results,
        /** @var LintStats */
        public array $stats,
    ) {}
}
