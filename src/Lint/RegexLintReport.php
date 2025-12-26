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

namespace RegexParser\Lint;

use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\RegexProblem;
use RegexParser\ValidationResult;

/**
 * Output from a lint run.
 *
 * @internal
 *
 * @phpstan-type LintIssue array{type: string, message: string, file: string, line: int, column?: int, position?: int, issueId?: string, hint?: string|null, source?: string, pattern?: string, regex?: string, analysis?: ReDoSAnalysis, validation?: ValidationResult}
 * @phpstan-type OptimizationEntry array{file: string, line: int, optimization: OptimizationResult, savings: int, source?: string}
 * @phpstan-type LintResult array{file: string, line: int, source?: string|null, pattern: string|null, location?: string|null, issues: array<LintIssue>, optimizations: array<OptimizationEntry>, problems: array<RegexProblem>}
 * @phpstan-type LintStats array{errors: int, warnings: int, optimizations: int}
 */
final readonly class RegexLintReport
{
    /**
     * @param array<LintResult> $results
     * @param LintStats         $stats
     */
    public function __construct(
        /**
         * @var array<LintResult>
         */
        public array $results,
        /**
         * @var LintStats
         */
        public array $stats,
    ) {}
}
