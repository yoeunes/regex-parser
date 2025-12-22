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

namespace RegexParser\Tests\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\TokenBasedExtractionStrategy;

/**
 * Comprehensive tests for regex pattern extraction with flags.
 *
 * These tests ensure that regex patterns with flags are properly extracted
 * and preserved in their original form to avoid confusion in linting output.
 */
final class TokenBasedExtractionStrategyFlagsTest extends TestCase
{
    private TokenBasedExtractionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new TokenBasedExtractionStrategy();
    }

    /**
     * Test various regex patterns with flags.
     */
    public function test_extract_regex_patterns_with_flags(): void
    {
        $patterns = [
            // Basic patterns
            'simple' => '/test/',
            'with_flags' => '/test/i',
            'with_delimiter' => '#test#',
            'unicode' => '/pattern/u',

            // Complex patterns from real world
            'phpstan_class' => '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+$/',
            'emoji_pattern' => '/{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu',
            'real_world_m_flag' => '/QUICK_CHECK = .*;/m',

            // Edge cases
            'escaped_delimiters' => '/\/complex\\\/pattern/m',
            'multiple_flags' => '/pattern/mx',
            'unicode_multiple' => '/test/iu',
            'different_delimiters' => '#pattern#',
            'quoted_delimiter' => '"pattern/m"',
            'mixed_delimiters' => '/pattern\\d/m',
            'nested_flags' => '/test/miux',
            'unicode_case_insensitive' => '/test/iu',
            'unicode_space' => '/[\w\s]+/u',
        ];

        foreach ($patterns as $name => $expectedPattern) {
            $phpCode = "<?php\npreg_match('$expectedPattern', \$subject);\n";
            $tempFile = tempnam(sys_get_temp_dir(), 'regex_test_'.$name);
            file_put_contents($tempFile, $phpCode);

            $results = $this->strategy->extract([$tempFile]);

            $this->assertCount(1, $results, "Should extract exactly one pattern for: $name");
            $this->assertSame($expectedPattern, $results[0]->pattern,
                "Pattern mismatch for: $name\n".
                "Expected: $expectedPattern\n".
                "Actual: $results[0]->pattern",
            );

            unlink($tempFile);
        }
    }

    /**
     * Test that pen emoji is not displayed for non-clickable patterns.
     */
    public function test_pen_emoji_not_displayed_for_non_clickable_patterns(): void
    {
        // Simple pattern without any special clickability
        $result = $this->strategy->extract(['test_simple.php' => "<?php\npreg_match('/pattern/', \$subject);\n"]);

        $this->assertCount(1, $result);
        $this->assertSame('/pattern/', $result[0]->pattern);

        // Complex pattern that should also not show pen
        $result = $this->strategy->extract(['test_complex.php' => "<?php\npreg_match('/complex_pattern.*;/m', \$subject);\n"]);

        $this->assertCount(1, $result);
        $this->assertStringNotContainsString('✏️', (string) var_export($result[0]));
    }

    /**
     * Test that flags are preserved in various edge cases.
     */
    public function test_edge_cases_with_flags(): void
    {
        $edgeCases = [
            'empty_string' => '',
            'null_delimiter' => null,
            'mixed_delimiters' => '/pattern\\d/m',
            'escaped_content' => '/pattern\\/\\/im',
            'unicode_emoji' => '/🙂/u',
            'nested_flags' => '/test/miux',
            'unicode_multiple_flags' => '/test/iux',
        ];

        foreach ($edgeCases as $name => $phpCode) {
            $tempFile = tempnam(sys_get_temp_dir(), 'edge_case_'.$name);
            file_put_contents($tempFile, "<?php\n$phpCode");

            $results = $this->strategy->extract([$tempFile]);

            $this->assertCount(1, $results, "Should handle edge case: $name");
            $this->assertNotEmpty($results[0]->pattern, "Pattern should not be empty for: $name");
        }
    }
}
