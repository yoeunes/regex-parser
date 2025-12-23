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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ReDoSProfileNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class AdvancedFeaturesComplianceTest extends TestCase
{
    #[DataProvider('provideRecursionSeeds')]
    public function test_sample_generator_handles_recursion(int $seed, string $expectedSample): void
    {
        $regex = Regex::create()->parse('/a(?R)?z/');
        $generator = new SampleGeneratorNodeVisitor();
        $generator->setSeed($seed);

        $this->assertSame($expectedSample, $regex->accept($generator));
    }

    public static function provideRecursionSeeds(): \Iterator
    {
        yield 'single depth' => [2, 'az'];
        yield 'double depth' => [5, 'aazz'];
        yield 'triple depth' => [1, 'aaazzz'];
    }

    #[DataProvider('provideControlVerbPatterns')]
    public function test_redos_analyzer_respects_commit(string $pattern, ReDoSSeverity $expected): void
    {
        $regex = Regex::create()->parse($pattern);
        $visitor = new ReDoSProfileNodeVisitor();
        $regex->accept($visitor);
        $result = $visitor->getResult();

        $this->assertSame($expected, $result['severity']);
    }

    public static function provideControlVerbPatterns(): \Iterator
    {
        yield 'commit' => ['/(a+(*COMMIT))+/', ReDoSSeverity::SAFE];
        yield 'prune' => ['/(a+(*PRUNE))+/', ReDoSSeverity::SAFE];
        yield 'skip' => ['/(a+(*SKIP))+/', ReDoSSeverity::SAFE];
    }

    #[DataProvider('provideRecursiveConditionPatterns')]
    public function test_recursive_condition_validation(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $validator = new ValidatorNodeVisitor();

        $regex->accept($validator); // Validation passed without exception.

        $this->assertGreaterThan(0, \strlen($pattern));
    }

    public static function provideRecursiveConditionPatterns(): \Iterator
    {
        yield 'explicit group recursion' => ['/((a))(?(R1)a|b)/'];
        yield 'root recursion check' => ['/(?(R)a|b)/'];
    }

    #[DataProvider('provideQuantifierPatterns')]
    public function test_php84_quantifier_missing_min_syntax(string $pattern, string $expectedCompiled): void
    {
        $regex = Regex::create()->parse($pattern);
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $regex->accept($compiler);

        $this->assertSame($expectedCompiled, $compiled, "PHP 8.4 quantifier syntax should compile correctly: {$pattern}");
    }

    public static function provideQuantifierPatterns(): \Iterator
    {
        yield 'missing_min_no_spaces' => ['/a{,3}/', '/a{,3}/'];
        yield 'missing_min_with_spaces' => ['/a{ , 3 }/', '/a{,3}/'];
        yield 'missing_min_with_single_space' => ['/a{ ,3}/', '/a{,3}/'];
        yield 'missing_max_with_spaces' => ['/a{2, }/', '/a{2,}/'];
        yield 'both_with_spaces' => ['/a{ 2 , 3 }/', '/a{2,3}/'];
        yield 'no_spaces' => ['/a{2,3}/', '/a{2,3}/'];
    }

    #[DataProvider('provideNewlineVerbPatterns')]
    public function test_newline_verbs(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $regex->accept($compiler);

        $this->assertSame($pattern, $compiled, "Newline verb should round-trip: {$pattern}");
    }

    public static function provideNewlineVerbPatterns(): \Iterator
    {
        yield 'cr_newline' => ['/(*CR)a/'];
        yield 'lf_newline' => ['/(*LF)a/'];
        yield 'crlf_newline' => ['/(*CRLF)a/'];
    }

    #[DataProvider('provideEncodingVerbPatterns')]
    public function test_encoding_verbs(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $regex->accept($compiler);

        $this->assertSame($pattern, $compiled, "Encoding verb should round-trip: {$pattern}");
    }

    public static function provideEncodingVerbPatterns(): \Iterator
    {
        yield 'utf8' => ['/(*UTF8)a/'];
        yield 'ucp' => ['/(*UCP)a/'];
    }

    #[DataProvider('provideMatchControlVerbPatterns')]
    public function test_match_control_verbs(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $regex->accept($compiler);

        $this->assertSame($pattern, $compiled, "Match control verb should round-trip: {$pattern}");
    }

    public static function provideMatchControlVerbPatterns(): \Iterator
    {
        yield 'notempty' => ['/(*NOTEMPTY)a+/'];
        yield 'notempty_atstart' => ['/(*NOTEMPTY_ATSTART)^a+/'];
    }

    #[DataProvider('provideUnicodePropertyPatterns')]
    public function test_unicode_properties(string $pattern, string $expected): void
    {
        $regex = Regex::create()->parse($pattern);
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $regex->accept($compiler);

        $this->assertSame($expected, $compiled, "Unicode property should compile to: {$expected}");
    }

    public static function provideUnicodePropertyPatterns(): \Iterator
    {
        yield 'general_category' => ['/\p{L}/', '/\p{L}/'];
        yield 'general_category_negated' => ['/\P{L}/', '/\p{^L}/'];
        yield 'script' => ['/\p{Arabic}/', '/\p{Arabic}/'];
        yield 'script_negated' => ['/\P{Arabic}/', '/\p{^Arabic}/'];
        yield 'property' => ['/\p{Alpha}/', '/\p{Alpha}/'];
        yield 'combined' => ['/[\p{L}\p{M}]/', '/[\p{L}\p{M}]/'];
        yield 'with_braces' => ['/\p{Latin}/', '/\p{Latin}/'];
        yield 'negated_with_braces' => ['/\P{Latin}/', '/\p{^Latin}/'];
        yield 'short_form' => ['/\pL/', '/\pL/'];
        yield 'short_form_negated' => ['/\PL/', '/\p{^L}/'];
    }

    #[DataProvider('providePcre84Features')]
    public function test_pcre_84_features_implemented(string $pattern, string $description): void
    {
        $regex = Regex::create();

        // Should parse without exception
        $ast = $regex->parse($pattern);

        // Should compile back to valid regex
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
        $compiled = $ast->accept($compiler);

        // Should validate without errors
        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator);

        // Ensure the pattern is preserved or normalized correctly
        $this->assertIsString($compiled, "Pattern {$pattern} should compile successfully: {$description}");
    }

    public static function providePcre84Features(): \Iterator
    {
        // 1. {,n} quantifier syntax (PHP 8.4+)
        yield 'omitted_min_no_spaces' => ['/a{,3}/', '{,n} quantifier syntax'];
        yield 'omitted_min_with_spaces' => ['/a{ , 3 }/', '{ , n } quantifier syntax'];
        yield 'omitted_min_single_space' => ['/a{ ,3}/', '{ ,n } quantifier syntax'];

        // 2. Spaces in quantifier braces
        yield 'spaces_in_quantifier' => ['/a{ 2 , 5 }/', 'spaces in quantifier braces'];
        yield 'spaces_both_missing' => ['/a{ , }/', 'both min and max omitted with spaces'];

        // 3. Newline convention verbs
        yield 'newline_cr' => ['/(*CR)a/', '(*CR) newline convention verb'];
        yield 'newline_lf' => ['/(*LF)b/', '(*LF) newline convention verb'];
        yield 'newline_crlf' => ['/(*CRLF)c/', '(*CRLF) newline convention verb'];

        // 4. Control verbs
        yield 'control_mark' => ['/(*MARK:label)a/', '(*MARK) control verb'];
        yield 'control_prune' => ['/(*PRUNE)b/', '(*PRUNE) control verb'];
        yield 'control_skip' => ['/(*SKIP)c/', '(*SKIP) control verb'];
        yield 'control_then' => ['/(*THEN)d/', '(*THEN) control verb'];

        // 5. Encoding control verbs
        yield 'encoding_utf8' => ['/(*UTF8)pattern/', '(*UTF8) encoding control verb'];
        yield 'encoding_ucp' => ['/(*UCP)test/', '(*UCP) encoding control verb'];

        // 6. Match control verbs
        yield 'match_notempty' => ['/(*NOTEMPTY)a+/', '(*NOTEMPTY) match control verb'];
        yield 'match_notempty_atstart' => ['/(*NOTEMPTY_ATSTART)^a+/', '(*NOTEMPTY_ATSTART) match control verb'];

        // 7. \R backreference (as char type, not backreference)
        yield 'r_char_type' => ['/\R/', '\\R as char type (line ending)'];

        // 8. Possessive quantifiers in char classes (edge cases - quantifiers not allowed in char classes)
        yield 'char_class_with_plus' => ['/[a+]/', 'plus literal in char class'];
        yield 'char_class_with_star' => ['/[b*]/', 'star literal in char class'];

        // 9. Extended Unicode properties
        yield 'unicode_extended_script' => ['/\p{Greek}/', 'extended Unicode script property'];
        yield 'unicode_extended_category' => ['/\p{Ll}/', 'extended Unicode category property'];
        yield 'unicode_extended_property' => ['/\p{Alpha}/', 'extended Unicode binary property'];

        // 10. Callouts
        yield 'callout_numeric' => ['/(?C1)abc/', 'numeric callout'];
        yield 'callout_string' => ['/(?C"debug")def/', 'string callout'];
        yield 'callout_named' => ['/(?Cmyfunc)xyz/', 'named callout'];

        // Additional combinations
        yield 'complex_quantifiers_and_verbs' => ['/(?C1)a{,3}(*MARK:pos)b{ 2 , 5 }(*PRUNE)/', 'complex combination of features'];
        yield 'unicode_with_newlines' => ['/(*CRLF)\p{L}+(*NOTEMPTY_ATSTART)/', 'Unicode properties with newline verbs'];
    }
}
