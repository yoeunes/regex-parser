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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class HighlighterTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    public function test_highlight_cli_contains_ansi_codes(): void
    {
        $highlighted = $this->regexService->highlight('/a+/', 'cli');

        $this->assertStringContainsString('a', $highlighted);
        $this->assertStringContainsString("\033[", $highlighted); // ANSI escape code
        $this->assertStringContainsString("\033[0m", $highlighted); // Reset
    }

    public function test_highlight_html_contains_span_tags(): void
    {
        $highlighted = $this->regexService->highlight('/a+/', 'html');

        $this->assertStringContainsString('<span', $highlighted);
        $this->assertStringContainsString('</span>', $highlighted);
        $this->assertStringContainsString('regex-literal', $highlighted);
        $this->assertStringContainsString('regex-quantifier', $highlighted);
    }

    public function test_highlight_html_escapes_special_chars(): void
    {
        $highlighted = $this->regexService->highlight('/<script>/', 'html');

        $this->assertStringContainsString('&lt;', $highlighted);
        $this->assertStringContainsString('&gt;', $highlighted);
        $this->assertStringNotContainsString('<script>', $highlighted);
    }

    public function test_highlight_cli_complex_pattern(): void
    {
        $highlighted = $this->regexService->highlight('/^[0-9]+(\w+)$/', 'cli');

        $this->assertStringContainsString("\033[1;34m", $highlighted); // Meta chars
        $this->assertStringContainsString("\033[1;33m", $highlighted); // Quantifiers
        $this->assertStringContainsString("\033[0;32m", $highlighted); // Types
    }

    public function test_highlight_html_complex_pattern(): void
    {
        $highlighted = $this->regexService->highlight('/^[0-9]+(\w+)$/', 'html');

        $this->assertStringContainsString('regex-meta', $highlighted);
        $this->assertStringContainsString('regex-quantifier', $highlighted);
        $this->assertStringContainsString('regex-type', $highlighted);
        $this->assertStringContainsString('regex-anchor', $highlighted);
    }
}
