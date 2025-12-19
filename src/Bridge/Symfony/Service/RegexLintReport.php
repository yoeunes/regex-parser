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
 */
final readonly class RegexLintReport
{
    /**
     * @param array<int, array{
     *     file: string,
     *     line: int,
     *     source?: string|null,
     *     pattern: string|null,
     *     location?: string|null,
     *     issues: array<int, array{type: string, message: string, file: string, line: int, hint?: string|null, source?: string}>,
     *     optimizations: array<int, array{file: string, line: int, optimization: object, savings: int, source?: string}>
     * }> $results
     * @param array{errors: int, warnings: int, optimizations: int} $stats
     */
    public function __construct(
        public array $results,
        public array $stats,
    ) {}
}
