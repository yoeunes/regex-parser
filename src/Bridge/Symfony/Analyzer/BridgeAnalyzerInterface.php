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
interface BridgeAnalyzerInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getPriority(): int;

    /**
     * @return array<int, BridgeReportSection>
     */
    public function analyze(BridgeRunContext $context): array;
}
