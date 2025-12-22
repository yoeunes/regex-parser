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

use PHPUnit\Framework\Attributes\DataProvider;
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
     * @return array<array{string, string, string}>
     */
    public static function regexPatternsWithFlagsProvider(): array
    {
        return [
            // Test various regex patterns with different flags
            ['/pattern/m', '/pattern/i', '/pattern/s', '/pattern/x', '/pattern/U', '/pattern/mx'],
            ['#/pattern#', '#{pattern}u', '~pattern~', '%pattern%'],
            ['{pattern}u', '{pattern}i', '{pattern}m', '{pattern}s', '{pattern}x'],
            ['/complex_pattern.*;/m', '/simple_with_flags/m', '/test/iu'],
            ['preg_match("/pattern/m", $subject)', 'preg_replace("/old/i", "new")'],
            // Edge cases: escaped delimiters, multiple flags
            ['\/complex\\\/pattern\/iu', '/\\/escaped\\//m', '"pattern-with-quotes/m'],
            // Unicode patterns
            ['/\p{L}+/u', '/\p{Ll}+/iu', '/[\w\s]+/u'],
            // Real-world patterns from the issue
            ['{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu', '/QUICK_CHECK = .*;/m'],
        ];
    }

    /**
     * @dataProvider regexPatternsWithFlagsProvider
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function testExtractRegexPatternsWithFlags(string $phpCode, string $expectedPattern): void
    {
        // Create temporary file with the PHP code
        $tempFile = tempnam(sys_get_temp_dir(), 'regex_test_');
        file_put_contents($tempFile, "<?php\n$phpCode");
        
        // Extract patterns
        $results = $this->strategy->extract([$tempFile]);
        
        // Verify extraction worked
        $this->assertCount(1, $results, "Should extract exactly one pattern from: $phpCode");
        
        // Verify pattern includes flags
        $actualPattern = $results[0]->pattern;
        $this->assertSame($expectedPattern, $actualPattern, 
            "Pattern mismatch for PHP code: $phpCode\n" .
            "Expected: $expectedPattern\n" .
            "Actual: $actualPattern"
        );
        
        // Clean up
        unlink($tempFile);
    }

    public function testExtractPatternWithMFlag(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/m", $subject);',
            '/pattern/m'
        );
    }

    public function testExtractPatternWithIFlag(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/i", $subject);',
            '/pattern/i'
        );
    }

    public function testExtractPatternWithSFlag(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/s", $subject);',
            '/pattern/s'
        );
    }

    public function testExtractPatternWithXFlag(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/x", $subject);',
            '/pattern/x'
        );
    }

    public function testExtractPatternWithUFlag(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/U", $subject);',
            '/pattern/U'
        );
    }

    public function testExtractPatternWithMultipleFlags(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/mx", $subject);',
            '/pattern/mx'
        );
    }

    public function testExtractPatternWithDifferentDelimiters(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("#pattern#", $subject);',
            '#pattern#'
        );
    }

    public function testExtractPatternWithUnicodeFlags(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("{pattern}u", $subject);',
            '{pattern}u'
        );
    }

    public function testExtractRealWorldPatterns(): void
    {
        // Test the actual patterns from the issue
        $this->testExtractRegexPatternsWithFlags(
            '{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu',
            '/{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu'
        );
        
        $this->testExtractRegexPatternsWithFlags(
            '/QUICK_CHECK = .*;/m',
            '/QUICK_CHECK = .*;/m'
        );
    }

    public function testExtractPatternWithEscapedDelimiters(): void
    {
        // Test edge cases with escaped delimiters
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/complex\\/pattern/m", $subject);',
            '/complex\\/pattern/m'
        );
    }

    public function testExtractPatternWithMultipleEscapedChars(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/\\/escaped\\/\\/pattern/m", $subject);',
            '/\\/escaped\\/\\/pattern/m'
        );
    }

    public function testExtractPatternWithQuotedDelimiters(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_replace("/pattern-with-quotes/m", "new")',
            '/pattern-with-quotes/m'
        );
    }

    public function testExtractUnicodeCharacterClasses(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/\p{L}+/u", $subject);',
            '/\p{L}+/u'
        );
    }

    public function testExtractUnicodeCharacterClassesWithCaseInsensitive(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/\p{Ll}+/iu", $subject);',
            '/\p{Ll}+/iu'
        );
    }

    public function testExtractPatternWithUnicodeSpaces(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/[\w\s]+/u", $subject);',
            '/[\w\s]+/u'
        );
    }

    public function testExtractPatternWithoutFlags(): void
    {
        // Test that patterns without flags still work
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/pattern/", $subject);',
            '/pattern/'
        );
    }

    public function testExtractComplexRealWorldPattern(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/complex_pattern.*;/m", $subject);',
            '/complex_pattern.*;/m'
        );
    }

    public function testExtractSimplePatternWithFlags(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/simple_with_flags/m", $subject);',
            '/simple_with_flags/m'
        );
    }

    public function testExtractPatternWithCaseInsensitiveUnicode(): void
    {
        $this->testExtractRegexPatternsWithFlags(
            'preg_match("/test/iu", $subject);',
            '/test/iu'
        );
    }
}