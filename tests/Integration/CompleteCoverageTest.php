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

/**
 * Comprehensive test to reach 100% code coverage.
 * This test exercises all uncovered code paths across visitors and parser.
 */
class CompleteCoverageTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    // Test CommentNode with all visitors
    public function test_comment_node_with_all_visitors(): void
    {
        $pattern = '/(?#this is a comment)abc/';
        
        // Test parse
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Test dump (DumperNodeVisitor)
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Comment', $dump);
        
        // Test explain (ExplainVisitor)
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        // Test generate (SampleGeneratorVisitor)
        $sample = $this->regex->generate($pattern);
        $this->assertStringContainsString('abc', $sample);
        
        // Test optimize (OptimizerNodeVisitor + CompilerNodeVisitor)
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        // Test validate (ValidatorNodeVisitor)
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test ConditionalNode with all visitors
    public function test_conditional_node_with_all_visitors(): void
    {
        $pattern = '/(?(?=a)b|c)/'; // Conditional with lookahead
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Conditional', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        // Skip generate - conditionals with lookahead may not be supported for sample generation
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test SubroutineNode with all visitors
    public function test_subroutine_node_with_all_visitors(): void
    {
        $pattern = '/(?<name>abc)(?&name)/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Subroutine', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        // Skip generate - subroutines not supported for sample generation
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test PcreVerbNode with all visitors
    public function test_pcre_verb_node_with_all_visitors(): void
    {
        $pattern = '/(*FAIL)abc/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('PcreVerb', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test OctalLegacyNode with all visitors
    public function test_octal_legacy_node_with_all_visitors(): void
    {
        $pattern = '/\07/'; // Octal legacy format
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test PosixClassNode with all visitors
    public function test_posix_class_node_with_all_visitors(): void
    {
        $pattern = '/[[:alnum:]]/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('PosixClass', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test OctalNode with all visitors
    public function test_octal_node_with_all_visitors(): void
    {
        $pattern = '/\o{101}/'; // Octal for 'A'
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test UnicodeNode with all visitors
    public function test_unicode_node_with_all_visitors(): void
    {
        $pattern = '/\u{41}/'; // Unicode for 'A'
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test UnicodePropNode with all visitors
    public function test_unicode_prop_node_with_all_visitors(): void
    {
        $pattern = '/\p{L}/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test negated Unicode property
    public function test_negated_unicode_prop_node(): void
    {
        $pattern = '/\P{L}/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
    }

    // Test KeepNode with all visitors
    public function test_keep_node_with_all_visitors(): void
    {
        $pattern = '/abc\Kdef/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Keep', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test BackrefNode with all visitors
    public function test_backref_node_with_all_visitors(): void
    {
        $pattern = '/(abc)\1/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Backref', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertStringContainsString('abc', $sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test RangeNode with all visitors
    public function test_range_node_with_all_visitors(): void
    {
        $pattern = '/[a-z]/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Range', $dump);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
        
        $sample = $this->regex->generate($pattern);
        $this->assertMatchesRegularExpression('/^[a-z]$/', $sample);
        
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test escaped literals in Lexer
    public function test_escaped_literals(): void
    {
        // Test various escaped characters
        $patterns = [
            '/\t/',  // Tab
            '/\n/',  // Newline
            '/\r/',  // Carriage return
            '/\f/',  // Form feed
            '/\v/',  // Vertical tab
            '/\e/',  // Escape
        ];
        
        foreach ($patterns as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $result = $this->regex->validate($pattern);
            $this->assertTrue($result->isValid);
        }
    }

    // Test Lexer with quote mode
    public function test_quote_mode(): void
    {
        $pattern = '/\Qabc.def\E/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $sample = $this->regex->generate($pattern);
        $this->assertStringContainsString('abc.def', $sample);
    }

    // Test Parser edge cases
    public function test_parser_group_modifiers(): void
    {
        // Non-capturing group
        $pattern = '/(?:abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Positive lookahead
        $pattern = '/(?=abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Negative lookahead
        $pattern = '/(?!abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Positive lookbehind
        $pattern = '/(?<=abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Negative lookbehind
        $pattern = '/(?<!abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        // Atomic group
        $pattern = '/(?>abc)/';
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
    }

    // Test RegexBuilder uncovered methods
    public function test_regex_builder_methods(): void
    {
        $pattern = '/test/i';
        
        // Test optimize method
        $optimized = $this->regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
        
        // Test dump method
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Regex', $dump);
    }

    // Test complex conditional patterns
    public function test_conditional_with_named_group(): void
    {
        $pattern = '/(?<foo>a)?(?(foo)b|c)/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Conditional', $dump);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }

    // Test subroutine with number reference
    public function test_subroutine_with_number(): void
    {
        $pattern = '/(abc)(?1)/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Subroutine', $dump);
    }

    // Test various PCRE verbs
    public function test_pcre_verbs(): void
    {
        $verbs = [
            '/(*ACCEPT)/',
            '/(*COMMIT)/',
            '/(*PRUNE)/',
            '/(*SKIP)/',
            '/(*THEN)/',
            '/(*MARK:test)/',
        ];
        
        foreach ($verbs as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $dump = $this->regex->dump($pattern);
            $this->assertStringContainsString('PcreVerb', $dump);
        }
    }

    // Test various POSIX classes
    public function test_posix_classes(): void
    {
        $classes = [
            '/[[:alpha:]]/',
            '/[[:digit:]]/',
            '/[[:xdigit:]]/',
            '/[[:upper:]]/',
            '/[[:lower:]]/',
            '/[[:space:]]/',
            '/[[:blank:]]/',
            '/[[:punct:]]/',
            '/[[:graph:]]/',
            '/[[:print:]]/',
            '/[[:cntrl:]]/',
        ];
        
        foreach ($classes as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $dump = $this->regex->dump($pattern);
            $this->assertNotEmpty($dump);
            
            $explanation = $this->regex->explain($pattern);
            $this->assertNotEmpty($explanation);
        }
    }

    // Test negated POSIX class
    public function test_negated_posix_class(): void
    {
        $pattern = '/[[:^digit:]]/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $explanation = $this->regex->explain($pattern);
        $this->assertNotEmpty($explanation);
    }

    // Test char class with various elements
    public function test_complex_char_class(): void
    {
        $pattern = '/[a-z0-9\d\w[:alpha:]]/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertNotEmpty($dump);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
    }

    // Test negated char class
    public function test_negated_char_class(): void
    {
        $pattern = '/[^abc]/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
    }

    // Test various quantifiers
    public function test_quantifiers(): void
    {
        $patterns = [
            '/a{3}/',      // Exactly 3
            '/a{2,5}/',    // Between 2 and 5
            '/a{2,}/',     // At least 2
            '/a*?/',       // Lazy zero or more
            '/a+?/',       // Lazy one or more
            '/a??/',       // Lazy optional
            '/a{2,5}?/',   // Lazy range
        ];
        
        foreach ($patterns as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $explanation = $this->regex->explain($pattern);
            $this->assertNotEmpty($explanation);
        }
    }

    // Test anchors and assertions
    public function test_anchors_and_assertions(): void
    {
        $patterns = [
            '/^abc/',      // Start of line
            '/abc$/',      // End of line
            '/\Aabc/',     // Start of string
            '/abc\Z/',     // End of string
            '/abc\z/',     // Absolute end
            '/\babc/',     // Word boundary
            '/\Babc/',     // Non-word boundary
        ];
        
        foreach ($patterns as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $explanation = $this->regex->explain($pattern);
            $this->assertNotEmpty($explanation);
        }
    }

    // Test char types
    public function test_char_types(): void
    {
        $patterns = [
            '/\d/',   // Digit
            '/\D/',   // Non-digit
            '/\w/',   // Word
            '/\W/',   // Non-word
            '/\s/',   // Whitespace
            '/\S/',   // Non-whitespace
            '/\h/',   // Horizontal whitespace
            '/\H/',   // Non-horizontal whitespace
            '/\v/',   // Vertical whitespace (in newer PCRE this is different from \v as escape)
            '/\V/',   // Non-vertical whitespace
        ];
        
        foreach ($patterns as $pattern) {
            $ast = $this->regex->parse($pattern);
            $this->assertNotNull($ast);
            
            $sample = $this->regex->generate($pattern);
            $this->assertNotEmpty($sample);
        }
    }

    // Test alternation
    public function test_alternation(): void
    {
        $pattern = '/abc|def|ghi/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $dump = $this->regex->dump($pattern);
        $this->assertStringContainsString('Alternation', $dump);
        
        $sample = $this->regex->generate($pattern);
        $this->assertMatchesRegularExpression('/^(abc|def|ghi)$/', $sample);
    }

    // Test dot
    public function test_dot(): void
    {
        $pattern = '/./';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $sample = $this->regex->generate($pattern);
        $this->assertNotEmpty($sample);
    }

    // Test named groups
    public function test_named_groups(): void
    {
        $pattern = '/(?P<name>abc)\k<name>/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $sample = $this->regex->generate($pattern);
        $this->assertStringContainsString('abc', $sample);
    }

    // Test relative backrefs
    public function test_relative_backrefs(): void
    {
        $pattern = '/(a)(b)\g{-1}/';
        
        $ast = $this->regex->parse($pattern);
        $this->assertNotNull($ast);
        
        $result = $this->regex->validate($pattern);
        $this->assertTrue($result->isValid);
    }
}
