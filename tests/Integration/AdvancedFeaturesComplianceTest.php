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
}
