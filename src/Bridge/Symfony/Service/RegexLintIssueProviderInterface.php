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

use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;

/**
 * Provides additional analysis issues for lint reporting.
 *
 * @internal
 */
interface RegexLintIssueProviderInterface
{
    public function getName(): string;

    public function getLabel(): string;

    public function isSupported(): bool;

    /**
     * @return list<AnalysisIssue>
     */
    public function analyze(): array;
}
