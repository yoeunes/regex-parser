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

namespace RegexParser\Bridge\Symfony\Analyzer;

/**
 * @internal
 */
final readonly class BridgeReportSection
{
    /**
     * @param array<string, int|string> $meta
     * @param array<int, BridgeNotice>  $summary
     * @param array<int, BridgeNotice>  $warnings
     * @param array<int, BridgeIssue>   $issues
     * @param array<int, string>        $suggestions
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $meta = [],
        public array $summary = [],
        public array $warnings = [],
        public array $issues = [],
        public array $suggestions = [],
    ) {}
}
