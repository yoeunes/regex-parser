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

namespace RegexParser\Output;

use RegexParser\Lint\RegexLintReport;

/**
 * Interface for output formatters.
 */
interface OutputFormatterInterface
{
    /**
     * Format and output the lint report.
     */
    public function format(RegexLintReport $report): string;

    /**
     * Get the name of this formatter.
     */
    public function getName(): string;

    /**
     * Check if this formatter supports the given format.
     */
    public function supports(string $format): bool;
}
