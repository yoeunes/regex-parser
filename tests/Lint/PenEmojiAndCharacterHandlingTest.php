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
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Node;
use RegexParser\Node\LiteralNode;

/**
 * Comprehensive tests for pen emoji display and non-printable character handling.
 */
final class PenEmojiAndCharacterHandlingTest extends TestCase
{
    private TokenBasedExtractionStrategy $strategy;
    private ExplainNodeVisitor $explainVisitor;
    private ConsoleHighlighterVisitor $highlightVisitor;
    private CompilerNodeVisitor $compilerVisitor;

    protected function setUp(): void
    {
        $this->strategy = new TokenBasedExtractionStrategy();
        $this->explainVisitor = new ExplainNodeVisitor();
        $this->highlightVisitor = new ConsoleHighlighterVisitor();
        $this->compilerVisitor = new CompilerNodeVisitor();
    }

    /**
     * @return array<array{string, string}>
     */
    public static function nonPrintableCharacterProvider(): array
    {
        return [
            'control_char' => ["\x00", "\x01", "\x1F", "\x7F"],
            'extended_ascii' => ["\x80", "\xFF", "\xFF"],
            'unicode_space' => ["\u{00A0}", "\u{2000}"],
            'emoji' => ["🙂", "😊", "🎉"],
        ];
    }

    /**
     * @return array<array{string, string}>
     */
    public static function regexPatternProvider(): array
    {
        return [
            // Basic patterns
            'simple' => '/test/',
            'with_flags' => '/test/i',
            'with_delimiter' => '#test#',
            'unicode' => '/pattern/u',
            
            // Complex patterns from real world
            'phpstan_class' => '/^[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*+$/',
            'emoji_pattern' => '{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu',
            'real_world_m_flag' => '/QUICK_CHECK = .*;/m',
            
            // Edge cases
            'escaped_delimiters' => '/\/complex\\\/pattern/m',
            'multiple_flags' => '/pattern/mx',
            'unicode_multiple' => '/test/iu',
        ];
    }

    /**
     * Test that pen emoji is not displayed for non-clickable patterns.
     */
    public function testPenEmojiNotDisplayedForNonClickablePatterns(): void
    {
        $pattern = '/test/';
        $line = 42;
        
        // Test without any special context - should not show pen
        $result = $this->strategy->extract(['test_non_clickable.php' => "<?php\npreg_match('$pattern', \$subject);\n"]);
        
        $this->assertCount(1, $result);
        $this->assertSame($pattern, $result[0]->pattern);
        $this->assertSame('/test/', $result[0]->pattern);
    }

    /**
     * Test non-printable characters are handled in ExplainNodeVisitor.
     */
    #[\DataProvider('nonPrintableCharacterProvider')]
    public function testExplainNodeVisitorHandlesNonPrintableCharacters(string $inputChar, string $expectedOutput): void
    {
        $charNode = new LiteralNode($inputChar, 0, 1);
        $result = $this->explainVisitor->visitLiteral($charNode);
        
        $this->assertSame($expectedOutput, $result);
    }

    /**
     * Test non-printable characters are handled in CompilerNodeVisitor.
     */
    #[\DataProvider('nonPrintableCharacterProvider')]
    public function testCompilerNodeVisitorHandlesNonPrintableCharacters(string $inputChar, string $expectedOutput): void
    {
        $charNode = new LiteralNode($inputChar, 0, 1);
        $result = $this->compilerVisitor->visitLiteral($charNode);
        
        $this->assertSame($expectedOutput, $result);
    }

    /**
     * Test non-printable characters are handled in ConsoleHighlighterVisitor.
     */
    #[\DataProvider('nonPrintableCharacterProvider')]
    public function testConsoleHighlighterVisitorHandlesNonPrintableCharacters(string $inputChar, string $expectedOutput): void
    {
        // Create a simple regex pattern to test highlighting
        $regex = new \RegexParser\Regex();
        $result = $this->highlightVisitor->visitLiteral(new LiteralNode($inputChar, 0, 1));
        
        $this->assertSame($expectedOutput, $result);
    }

    /**
     * Test regex patterns with flags are extracted correctly.
     */
    public function testRegexPatternsWithFlags(): void
    {
        foreach (self::regexPatternProvider() as $name => $expectedPattern) {
            $phpCode = "<?php\npreg_match('$expectedPattern', \$subject);\n";
            $tempFile = tempnam(sys_get_temp_dir(), 'regex_test_');
            file_put_contents($tempFile, $phpCode);
            
            $results = $this->strategy->extract([$tempFile]);
            
            $this->assertCount(1, $results, "Should extract exactly one pattern for: $name");
            $this->assertSame($expectedPattern, $results[0]->pattern, "Pattern mismatch for: $name");
            
            unlink($tempFile);
        }
    }

    /**
     * Test that character ranges are handled correctly.
     */
    public function testCharacterRangesInExplainVisitor(): void
    {
        $testPatterns = [
            '/[\x00-\x1F]',  // Control characters
            '/[\x7F-\xFF]',  // Extended ASCII
            '/[\u{2000-\u{2FFF}]',  // Unicode range
        ];
        
        foreach ($testPatterns as $pattern) {
            $regex = new \RegexParser\Regex();
            $ast = $regex->parse($pattern);
            $explanation = $ast->accept($this->explainVisitor);
            
            // Should not contain weird characters in the explanation
            $this->assertStringNotContainsString("\xEF\xBF\xBD", $explanation, 
                "Explanation for pattern '$pattern' should not contain weird characters");
        }
    }

    /**
     * Test edge cases with special characters and delimiters.
     */
    public function testEdgeCasesWithSpecialCharacters(): void
    {
        $edgeCases = [
            'empty_string' => '',
            'null_delimiter' => null,
            'mixed_delimiters' => '/pattern\\d/m',
            'escaped_content' => '/pattern\\/\\/im',
            'unicode_emoji' => '/🙂/u',
            'nested_flags' => '/test/miux',
        ];
        
        foreach ($edgeCases as $name => $phpCode) {
            $tempFile = tempnam(sys_get_temp_dir(), 'edge_case_');
            file_put_contents($tempFile, "<?php\npreg_match('$phpCode', \$subject);\n");
            
            $results = $this->strategy->extract([$tempFile]);
            
            $this->assertCount(1, $results, "Should handle edge case: $name");
            
            unlink($tempFile);
        }
    }

    /**
     * Test regression cases for the specific issues we fixed.
     */
    public function testRegressionForPenEmojiAndCharacterEncoding(): void
    {
        // Test the specific patterns from the original issue
        $testFile = tempnam(sys_get_temp_dir(), 'regression_test');
        file_put_contents($testFile, "<?php\n");
        file_put_contents($testFile, "        \$fs->dumpFile(\$file, preg_replace('/QUICK_CHECK = .*;/m', \"QUICK_CHECK = {\$quickCheck};\", \$fs->readFile(\$file)));\n");
        file_put_contents($testFile, "        preg_match('{^(?<codePoints>[\\w ]+) +; [\\w-]+ +# (?<emoji>.+) E\\d+\\.\\d+ ?(?<name>.+)$}Uu', \$line, \$matches);\n");
        
        $results = $this->strategy->extract([$testFile]);
        
        $this->assertCount(2, $results, 'Should extract 2 patterns from regression test');
        
        // Verify first pattern has Uu flags and complex pattern
        $this->assertStringContainsString('}Uu', $results[0]->pattern);
        $this->assertStringContainsString('Pattern is complex', $results[0]->getWarnings()[0]['message'] ?? '');
        
        // Verify second pattern has m flag
        $this->assertStringContainsString('/m', $results[1]->pattern);
        $this->assertStringContainsString('useless', $results[1]->getWarnings()[1]['message'] ?? '');
        
        unlink($testFile);
    }
}