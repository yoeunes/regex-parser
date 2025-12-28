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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class RegexAnalysisServiceClassTest extends TestCase
{
    public function test_regex_analysis_service_class_instantiation(): void
    {
        $regex = Regex::create();
        $service = new RegexAnalysisService($regex);
        $this->assertInstanceOf(RegexAnalysisService::class, $service);
    }

    public function test_regex_analysis_service_with_options(): void
    {
        $regex = Regex::create();
        $service = new RegexAnalysisService(
            $regex,
            null,  // extractor
            50,    // warningThreshold
            ReDoSSeverity::HIGH->value,
            [],     // ignoredPatterns
            [],     // redosIgnoredPatterns
            false,    // ignoreParseErrors
        );
        $this->assertInstanceOf(RegexAnalysisService::class, $service);
    }
}
