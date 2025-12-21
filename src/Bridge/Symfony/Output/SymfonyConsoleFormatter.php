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

namespace RegexParser\Bridge\Symfony\Output;

use RegexParser\Lint\Formatter\ConsoleFormatter;
use RegexParser\Lint\Formatter\OutputConfiguration;
use RegexParser\Lint\RegexAnalysisService;

/**
 * Symfony-specific console output formatter.
 *
 * Wraps the main ConsoleFormatter with Symfony-specific configuration.
 */
final class SymfonyConsoleFormatter extends ConsoleFormatter
{
    public function __construct(RegexAnalysisService $analysisService)
    {
        parent::__construct($analysisService, new OutputConfiguration());
    }
}
