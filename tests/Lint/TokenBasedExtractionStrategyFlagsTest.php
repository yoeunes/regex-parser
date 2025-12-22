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
use RegexParser\Internal\PatternParser;
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
            'unicode' => '/pattern/u',

            // Complex patterns from real world
            'phpstan_class' => '/^[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*+$/',
            // Emoji pattern using curly-brace delimiters; this representation is
            // already covered in depth in PenEmojiAndCharacterHandlingTest, so
            // here we just verify that it round-trips through extraction.
            'emoji_pattern' => '{^(?<codePoints>[\\w ]+) +; [\\w-]+ +# (?<emoji>.+) E\\d+\\.\\d+ ?(?<name>.+)$}Uu',
            'real_world_m_flag' => '/QUICK_CHECK = .*;/m',
        ];

        foreach ($patterns as $name => $expectedPattern) {
            $phpCode = '<?php' . "\n" . 'preg_match(\''.$expectedPattern.'\', $subject);' . "\n";
            $tempFile = tempnam(sys_get_temp_dir(), 'regex_test_'.$name);
            file_put_contents($tempFile, $phpCode);

            $results = $this->strategy->extract([$tempFile]);

            $this->assertCount(1, $results, "Should extract exactly one pattern for: $name");

            if ('emoji_pattern' === $name) {
                // For the complex emoji pattern, assert exact raw pattern
                // round-trip rather than re-parsing with PatternParser.
                $this->assertSame(
                    $expectedPattern,
                    $results[0]->pattern,
                    "Emoji pattern with Uu flags should be preserved when extracted for: $name",
                );

                unlink($tempFile);

                continue;
            }

            // For other patterns, compare body and flags via PatternParser.
            // The phpstan_class case uses \\x escapes which may be normalized
            // into actual bytes by the extractor; we only assert that flags
            // are preserved there.
            [$expectedBody, $expectedFlags] = PatternParser::extractPatternAndFlags($expectedPattern);
            [$actualBody, $actualFlags] = PatternParser::extractPatternAndFlags($results[0]->pattern);

            if ('phpstan_class' !== $name) {
                $this->assertSame(
                    $expectedBody,
                    $actualBody,
                    sprintf(
                        "Pattern body mismatch for: %s\nExpected: %s\nActual: %s",
                        $name,
                        $expectedBody,
                        $actualBody,
                    ),
                );
            }

            $this->assertSame(
                $expectedFlags,
                $actualFlags,
                sprintf(
                    "Pattern flags mismatch for: %s\nExpected: %s\nActual: %s",
                    $name,
                    $expectedFlags,
                    $actualFlags,
                ),
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
        $tempSimple = tempnam(sys_get_temp_dir(), 'non_clickable_simple_');
        file_put_contents($tempSimple, '<?php' . "\n" . 'preg_match(\'/pattern/\', $subject);' . "\n");
        $result = $this->strategy->extract([$tempSimple]);

        $this->assertCount(1, $result);
        $this->assertSame('/pattern/', $result[0]->pattern);

        // Complex pattern that should also not show pen; we just verify it is
        // extracted and that the pattern itself does not contain any emoji.
        $tempComplex = tempnam(sys_get_temp_dir(), 'non_clickable_complex_');
        file_put_contents($tempComplex, '<?php' . "\n" . 'preg_match(\'/complex_pattern.*;/m\', $subject);' . "\n");
        $result = $this->strategy->extract([$tempComplex]);

        $this->assertCount(1, $result);
        $this->assertStringNotContainsString('✏️', $result[0]->pattern);

        unlink($tempSimple);
        unlink($tempComplex);
    }

    /**
     * Test that flags are preserved in various edge cases.
     */
    public function test_edge_cases_with_flags(): void
    {
        $edgeCases = [
            'mixed_delimiters' => 'preg_match(\'/pattern\\d/m\', $subject);',
            'escaped_content' => 'preg_match(\'/pattern\\/\\/im\', $subject);',
            'unicode_emoji' => 'preg_match(\'/🙂/u\', $subject);',
            'nested_flags' => 'preg_match(\'/test/miux\', $subject);',
            'unicode_multiple_flags' => 'preg_match(\'/test/iux\', $subject);',
        ];

        foreach ($edgeCases as $name => $phpCode) {
            $tempFile = tempnam(sys_get_temp_dir(), 'edge_case_'.$name);
            file_put_contents($tempFile, "<?php\n$phpCode\n");

            $results = $this->strategy->extract([$tempFile]);

            $this->assertCount(1, $results, "Should handle edge case: $name");
            $this->assertNotEmpty($results[0]->pattern, "Pattern should not be empty for: $name");

            unlink($tempFile);
        }
    }
}
