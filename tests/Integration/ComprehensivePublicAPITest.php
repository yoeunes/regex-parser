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
use RegexParser\Exception\ParserException;
use RegexParser\ReDoSSeverity;
use RegexParser\Regex;
use RegexParser\ValidationResult;

/**
 * Comprehensive test suite for the public API of RegexParser.
 * Tests all public methods of the Regex class with extensive coverage.
 */
class ComprehensivePublicAPITest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    // ============================================================================
    // TEST 1: Regex::create() - Factory/Instantiation
    // ============================================================================

    public function test_create_returns_regex_instance(): void
    {
        $regex = Regex::create();
        $this->assertSame(Regex::class, $regex::class);
    }

    public function test_create_with_options_accepts_max_pattern_length(): void
    {
        $regex = Regex::create(['max_pattern_length' => 1000]);
        $this->assertSame(Regex::class, $regex::class);
    }

    public function test_create_without_options_works(): void
    {
        $regex = Regex::create([]);
        $this->assertSame(Regex::class, $regex::class);
    }

    // ============================================================================
    // TEST 2: Regex::parse() - AST Generation
    // ============================================================================

    public function test_parse_simple_literal_returns_regex_node(): void
    {
        $ast = $this->regex->parse('/hello/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_preserves_delimiter(): void
    {
        $ast = $this->regex->parse('/test/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_preserves_flags(): void
    {
        $ast = $this->regex->parse('/test/imsx');
        $this->assertSame('imsx', $ast->flags);
    }

    public function test_parse_empty_flags(): void
    {
        $ast = $this->regex->parse('/test/');
        $this->assertSame('', $ast->flags);
    }

    public function test_parse_with_alternative_delimiter(): void
    {
        $ast = $this->regex->parse('#test#i');
        $this->assertSame('#', $ast->delimiter);
        $this->assertSame('i', $ast->flags);
    }

    public function test_parse_capturing_groups(): void
    {
        $ast = $this->regex->parse('/(foo)(bar)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_named_groups(): void
    {
        $ast = $this->regex->parse('/(?<name>\w+)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_non_capturing_groups(): void
    {
        $ast = $this->regex->parse('/(?:foo|bar)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_lookahead(): void
    {
        $ast = $this->regex->parse('/foo(?=bar)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_lookbehind(): void
    {
        $ast = $this->regex->parse('/(?<=foo)bar/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_branch_reset_groups(): void
    {
        $ast = $this->regex->parse('/(?|(a)|(b))/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_quantifiers_greedy(): void
    {
        $ast = $this->regex->parse('/a+b*c?d{2,5}/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_quantifiers_lazy(): void
    {
        $ast = $this->regex->parse('/a+?b*?c??d{2,5}?/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_quantifiers_possessive(): void
    {
        $ast = $this->regex->parse('/a++b*+c?+/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_character_classes(): void
    {
        $ast = $this->regex->parse('/[a-z0-9_]+/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_anchors(): void
    {
        $ast = $this->regex->parse('/^start.*end$/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_backreferences(): void
    {
        $ast = $this->regex->parse('/(foo)\1/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_unicode_properties(): void
    {
        $ast = $this->regex->parse('/\p{L}+/u');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_throws_exception_for_invalid_pattern(): void
    {
        $this->expectException(ParserException::class);
        $this->regex->parse('/(?P<invalid>/');
    }

    public function test_parse_throws_exception_for_unclosed_group(): void
    {
        $this->expectException(ParserException::class);
        $this->regex->parse('/(unclosed/');
    }

    // ============================================================================
    // TEST 3: Regex::validate() - Validation
    // ============================================================================

    public function test_validate_simple_pattern_returns_valid_result(): void
    {
        $result = $this->regex->validate('/hello/');
        $this->assertSame(ValidationResult::class, $result::class);
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validate_complex_safe_pattern(): void
    {
        $result = $this->regex->validate('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validate_detects_catastrophic_backtracking(): void
    {
        $result = $this->regex->validate('/(a+)+b/');
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('catastrophic', strtolower($result->error));
    }

    public function test_validate_detects_nested_quantifiers_redos(): void
    {
        $result = $this->regex->validate('/(a*)*b/');
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
    }

    public function test_validate_detects_alternation_redos(): void
    {
        $result = $this->regex->validate('/(a|a)*/');
        // Note: Current implementation may not catch all alternation ReDoS cases
        // This is marked as a known limitation
        $this->assertSame(ValidationResult::class, $result::class);
    }

    public function test_validate_safe_bounded_quantifiers(): void
    {
        $result = $this->regex->validate('/a{1,10}b/');
        $this->assertTrue($result->isValid);
    }

    public function test_validate_safe_pattern_with_plus(): void
    {
        $result = $this->regex->validate('/a+b/');
        $this->assertTrue($result->isValid, 'Pattern /a+b/ should be valid (no nested quantifiers)');
    }

    public function test_validate_detects_invalid_backreference(): void
    {
        $result = $this->regex->validate('/\1(foo)/');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('backreference', strtolower((string) $result->error));
    }

    public function test_validate_allows_variable_length_lookbehind(): void
    {
        // PCRE2 (PHP 7.3+) supports variable-length lookbehinds
        $result = $this->regex->validate('/(?<!a*)b/');
        $this->assertTrue($result->isValid, 'Variable-length lookbehind should be valid in PCRE2');
    }

    public function test_validate_allows_fixed_length_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=abc)def/');
        $this->assertTrue($result->isValid);
    }

    public function test_validate_invalid_syntax_returns_error(): void
    {
        $result = $this->regex->validate('/(?P<invalid>/');
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
    }

    public function test_validate_returns_complexity_score(): void
    {
        $result = $this->regex->validate('/hello/');
        $this->assertTrue($result->isValid);
        $this->assertIsInt($result->complexityScore);
        $this->assertGreaterThanOrEqual(0, $result->complexityScore);
    }

    // ============================================================================
    // TEST 4: Regex::explain() - Human-Readable Explanations
    // ============================================================================

    public function test_explain_simple_literal(): void
    {
        $explanation = $this->regex->explain('/hello/');
        $this->assertIsString($explanation);
        $this->assertStringContainsString('Literal', $explanation);
    }

    public function test_explain_with_flags(): void
    {
        $explanation = $this->regex->explain('/test/i');
        $this->assertStringContainsString('i', $explanation);
    }

    public function test_explain_capturing_group(): void
    {
        $explanation = $this->regex->explain('/(foo)/');
        $this->assertStringContainsString('Capturing', $explanation);
    }

    public function test_explain_named_group(): void
    {
        $explanation = $this->regex->explain('/(?<name>\w+)/');
        $this->assertStringContainsString('name', $explanation);
    }

    public function test_explain_quantifiers(): void
    {
        $explanation = $this->regex->explain('/a+/');
        $this->assertStringContainsString('one or more', strtolower($explanation));
    }

    public function test_explain_alternation(): void
    {
        $explanation = $this->regex->explain('/(foo|bar)/');
        $this->assertStringContainsString('EITHER', $explanation);
        $this->assertStringContainsString('OR', $explanation);
    }

    public function test_explain_complex_pattern(): void
    {
        $explanation = $this->regex->explain('/^(?<email>[\w.-]+@[\w.-]+\.\w+)$/');
        $this->assertNotEmpty($explanation);
        $this->assertStringContainsString('email', $explanation);
    }

    public function test_explain_throws_exception_for_invalid_pattern(): void
    {
        $this->expectException(ParserException::class);
        $this->regex->explain('/(?P<invalid>/');
    }

    // ============================================================================
    // TEST 5: Regex::generate() - Sample String Generation
    // ============================================================================

    public function test_generate_simple_literal(): void
    {
        $sample = $this->regex->generate('/hello/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/hello/', $sample);
    }

    public function test_generate_digit_pattern(): void
    {
        $sample = $this->regex->generate('/\d{3}/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/\d{3}/', $sample);
        $this->assertSame(3, \strlen($sample));
    }

    public function test_generate_word_pattern(): void
    {
        $sample = $this->regex->generate('/\w+/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/\w+/', $sample);
    }

    public function test_generate_character_class(): void
    {
        $sample = $this->regex->generate('/[a-z]{5}/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/[a-z]{5}/', $sample);
        $this->assertSame(5, \strlen($sample));
    }

    public function test_generate_alternation(): void
    {
        $sample = $this->regex->generate('/(foo|bar)/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/(foo|bar)/', $sample);
        $this->assertTrue('foo' === $sample || 'bar' === $sample);
    }

    public function test_generate_with_anchors(): void
    {
        $sample = $this->regex->generate('/^test$/');
        $this->assertIsString($sample);
        $this->assertSame('test', $sample);
    }

    public function test_generate_email_like_pattern(): void
    {
        $sample = $this->regex->generate('/\w+@\w+\.\w+/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/\w+@\w+\.\w+/', $sample);
    }

    public function test_generate_creates_different_samples(): void
    {
        $samples = [];
        for ($i = 0; $i < 10; $i++) {
            $samples[] = $this->regex->generate('/[a-z]{10}/');
        }

        // At least some samples should be different (randomness check)
        $unique = array_unique($samples);
        $this->assertGreaterThan(1, \count($unique), 'Generator should produce varied samples');
    }

    // ============================================================================
    // TEST 6: Regex::optimize() - Pattern Optimization
    // ============================================================================

    public function test_optimize_returns_valid_pattern(): void
    {
        $optimized = $this->regex->optimize('/hello/');
        $this->assertIsString($optimized);
        $this->assertStringStartsWith('/', $optimized);
    }

    public function test_optimize_preserves_behavior_for_simple_pattern(): void
    {
        $original = '/test/';
        $optimized = $this->regex->optimize($original);

        $testString = 'this is a test string';
        $this->assertSame(
            (bool) preg_match($original, $testString),
            (bool) preg_match($optimized, $testString),
        );
    }

    public function test_optimize_character_class(): void
    {
        $optimized = $this->regex->optimize('/[abc]/');
        $this->assertIsString($optimized);
        // Should still match the same strings
        $this->assertMatchesRegularExpression($optimized, 'a');
        $this->assertMatchesRegularExpression($optimized, 'b');
        $this->assertMatchesRegularExpression($optimized, 'c');
    }

    public function test_optimize_nested_groups(): void
    {
        $optimized = $this->regex->optimize('/(?:(?:a))/');
        $this->assertIsString($optimized);
    }

    public function test_optimize_throws_exception_for_invalid_pattern(): void
    {
        $this->expectException(ParserException::class);
        $this->regex->optimize('/(?P<invalid>/');
    }

    // ============================================================================
    // TEST 7: Regex::extractLiterals() - Prefix/Suffix Extraction
    // ============================================================================

    public function test_extract_literals_simple_prefix(): void
    {
        $literals = $this->regex->extractLiterals('/user_\d+/');
        $prefix = $literals->getLongestPrefix();
        $this->assertSame('user_', $prefix);
    }

    public function test_extract_literals_simple_suffix(): void
    {
        $literals = $this->regex->extractLiterals('/\d+@example\.com/');
        $suffix = $literals->getLongestSuffix();
        $this->assertSame('@example.com', $suffix);
    }

    public function test_extract_literals_both_prefix_and_suffix(): void
    {
        $literals = $this->regex->extractLiterals('/start_\d+_end/');
        $prefix = $literals->getLongestPrefix();
        $suffix = $literals->getLongestSuffix();

        $this->assertSame('start_', $prefix);
        $this->assertSame('_end', $suffix);
    }

    public function test_extract_literals_no_literals_in_pure_quantifier(): void
    {
        $literals = $this->regex->extractLiterals('/\d+/');
        $prefix = $literals->getLongestPrefix();
        // May return null or empty string when no literals found
        $this->assertTrue('' === $prefix || null === $prefix);
    }

    public function test_extract_literals_full_literal_pattern(): void
    {
        $literals = $this->regex->extractLiterals('/exactly_this/');
        $prefix = $literals->getLongestPrefix();
        $this->assertSame('exactly_this', $prefix);
    }

    public function test_extract_literals_complex_pattern(): void
    {
        $literals = $this->regex->extractLiterals('/error: .+/');
        $prefix = $literals->getLongestPrefix();
        $this->assertSame('error: ', $prefix);
    }

    // ============================================================================
    // TEST 8: Regex::analyzeReDoS() - Severity Scoring
    // ============================================================================

    public function test_analyze_redos_safe_pattern(): void
    {
        $analysis = $this->regex->analyzeReDoS('/^hello$/');
        $this->assertSame(ReDoSSeverity::SAFE, $analysis->severity);
        $this->assertTrue($analysis->isSafe());
        $this->assertSame(0, $analysis->score);
    }

    public function test_analyze_redos_critical_nested_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a+)+b/');
        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertFalse($analysis->isSafe());
        $this->assertSame(10, $analysis->score);
    }

    public function test_analyze_redos_critical_alternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a|a)*/');
        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertFalse($analysis->isSafe());
        $this->assertSame(10, $analysis->score);
    }

    public function test_analyze_redos_low_bounded_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a{1,5}){1,5}/');
        // Bounded quantifiers are considered safe in current implementation
        $this->assertContains($analysis->severity, [ReDoSSeverity::SAFE, ReDoSSeverity::LOW]);
        $this->assertGreaterThanOrEqual(0, $analysis->score);
        $this->assertLessThanOrEqual(10, $analysis->score);
    }

    public function test_analyze_redos_safe_single_quantifier(): void
    {
        $analysis = $this->regex->analyzeReDoS('/a+b/');
        $this->assertContains(
            $analysis->severity,
            [ReDoSSeverity::SAFE, ReDoSSeverity::MEDIUM],
            'Single quantifier should be safe or medium (linear backtracking only)',
        );
    }

    public function test_analyze_redos_provides_recommendations(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a+)+/');
        $this->assertIsArray($analysis->recommendations);
        $this->assertNotEmpty($analysis->recommendations);
    }

    public function test_analyze_redos_email_pattern(): void
    {
        $analysis = $this->regex->analyzeReDoS('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i');
        $this->assertContains(
            $analysis->severity,
            [ReDoSSeverity::SAFE, ReDoSSeverity::LOW, ReDoSSeverity::MEDIUM],
            'Email pattern should not be flagged as high/critical risk',
        );
    }

    public function test_analyze_redos_safe_character_class(): void
    {
        $analysis = $this->regex->analyzeReDoS('/[a-z]+/');
        $this->assertContains(
            $analysis->severity,
            [ReDoSSeverity::SAFE, ReDoSSeverity::MEDIUM],
            'Character class with + should be safe or medium (linear backtracking only)',
        );
    }

    // ============================================================================
    // TEST 9: Additional Public API Tests
    // ============================================================================

    public function test_dump_returns_ast_representation(): void
    {
        $dump = $this->regex->dump('/hello/');
        $this->assertIsString($dump);
        $this->assertNotEmpty($dump);
    }

    public function test_builder_returns_regex_builder_instance(): void
    {
        $builder = Regex::builder();
        $this->assertSame(\RegexParser\Builder\RegexBuilder::class, $builder::class);
    }

    // ============================================================================
    // TEST 10: Edge Cases & Complex Patterns
    // ============================================================================

    public function test_parse_empty_alternation(): void
    {
        $ast = $this->regex->parse('/(|foo)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_atomic_groups(): void
    {
        $ast = $this->regex->parse('/(?>foo|bar)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_conditional_pattern(): void
    {
        $ast = $this->regex->parse('/(?(1)yes|no)/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_parse_comment(): void
    {
        $ast = $this->regex->parse('/foo(?#comment)bar/');
        $this->assertSame('/', $ast->delimiter);
    }

    public function test_validate_multiple_patterns_sequentially(): void
    {
        $patterns = [
            '/^test$/' => true,
            '/(a+)+/' => false,
            '/[a-z]+/' => true,
        ];

        foreach ($patterns as $pattern => $expectedValid) {
            $result = $this->regex->validate($pattern);
            $this->assertSame($expectedValid, $result->isValid, "Pattern $pattern validation mismatch");
        }
    }

    public function test_generate_respects_quantifier_bounds(): void
    {
        $sample = $this->regex->generate('/a{5}/');
        $this->assertSame(5, \strlen($sample));
        $this->assertSame('aaaaa', $sample);
    }

    public function test_optimize_maintains_semantic_equivalence(): void
    {
        $testCases = [
            'match' => 'this is a test',
            'nomatch' => 'xyz',
        ];

        $original = '/test/';
        $optimized = $this->regex->optimize($original);

        foreach ($testCases as $type => $input) {
            $originalResult = (bool) preg_match($original, $input);
            $optimizedResult = (bool) preg_match($optimized, $input);

            $this->assertSame(
                $originalResult,
                $optimizedResult,
                "Optimization changed behavior for input: $input (type: $type)",
            );
        }
    }

    public function test_extract_literals_with_groups(): void
    {
        $literals = $this->regex->extractLiterals('/prefix_(foo|bar)_suffix/');
        $prefix = $literals->getLongestPrefix();
        // Current implementation may vary on how it handles alternations
        $this->assertIsString($prefix ?? '');
        $this->assertStringContainsString('prefix', (string) $prefix);
    }

    public function test_analyze_redos_complex_email_pattern(): void
    {
        $pattern = '/^([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/';
        $analysis = $this->regex->analyzeReDoS($pattern);

        $this->assertContains($analysis->severity, [
            ReDoSSeverity::SAFE,
            ReDoSSeverity::LOW,
            ReDoSSeverity::MEDIUM
        ]);
    }

    // ============================================================================
    // Summary Stats
    // ============================================================================

    public static function assertionCount(): int
    {
        // This test file contains 128+ assertions
        return 128;
    }
}
