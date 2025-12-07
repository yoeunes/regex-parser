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
        $highlighted = $this->regexService->highlightCli('/a+/');

        $this->assertTrue(strpos($highlighted, 'a') !== false);
        $this->assertTrue(strpos($highlighted, "\033[") !== false); // ANSI escape code
        $this->assertTrue(strpos($highlighted, "\033[0m") !== false); // Reset
    }

    public function test_highlight_html_contains_span_tags(): void
    {
        $highlighted = $this->regexService->highlightHtml('/a+/');

        $this->assertTrue(strpos($highlighted, '<span') !== false);
        $this->assertTrue(strpos($highlighted, '</span>') !== false);
        $this->assertTrue(strpos($highlighted, 'regex-literal') !== false);
        $this->assertTrue(strpos($highlighted, 'regex-quantifier') !== false);
    }

    public function test_highlight_html_escapes_special_chars(): void
    {
        $highlighted = $this->regexService->highlightHtml('/<script>/');

        $this->assertTrue(strpos($highlighted, '&lt;') !== false);
        $this->assertTrue(strpos($highlighted, '&gt;') !== false);
        $this->assertFalse(strpos($highlighted, '<script>') !== false);
    }

    public function test_highlight_cli_complex_pattern(): void
    {
        $highlighted = $this->regexService->highlightCli('/^[0-9]+(\w+)$/');

        $this->assertTrue(strpos($highlighted, "\033[1;34m") !== false); // Meta chars
        $this->assertTrue(strpos($highlighted, "\033[1;33m") !== false); // Quantifiers
        $this->assertTrue(strpos($highlighted, "\033[0;32m") !== false); // Types
    }

    public function test_highlight_html_complex_pattern(): void
    {
        $highlighted = $this->regexService->highlightHtml('/^[0-9]+(\w+)$/');

        $this->assertTrue(strpos($highlighted, 'regex-meta') !== false);
        $this->assertTrue(strpos($highlighted, 'regex-quantifier') !== false);
        $this->assertTrue(strpos($highlighted, 'regex-type') !== false);
        $this->assertTrue(strpos($highlighted, 'regex-anchor') !== false);
    }
}