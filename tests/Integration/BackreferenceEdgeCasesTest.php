<?php

declare(strict_types=1);

namespace Yoeunes\RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

final class BackreferenceEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    private function roundTrip(string $pattern): string
    {
        $ast = $this->regex->parse($pattern);
        $compiler = new CompilerNodeVisitor();
        
        return $ast->accept($compiler);
    }

    public function testBasicBackreference(): void
    {
        $pattern = '/(a)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled, 'Basic backreference should round-trip correctly');
        
        $this->assertMatchesRegularExpression($pattern, 'aa');
        $this->assertDoesNotMatchRegularExpression($pattern, 'ab');
    }

    public function testDoubleDigitBackreference(): void
    {
        $pattern = '/(\d\d)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, '1212');
        $this->assertDoesNotMatchRegularExpression($pattern, '1234');
    }

    public function testBackreferenceWithQuantifier(): void
    {
        $pattern = '/([a-z])\1{2}/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'aaa');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abc');
    }

    public function testBackreferenceToAlternation(): void
    {
        $pattern = '/(foo|bar)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'foofoo');
        $this->assertMatchesRegularExpression($pattern, 'barbar');
        $this->assertDoesNotMatchRegularExpression($pattern, 'foobar');
    }

    public function testMultipleDifferentBackreferences(): void
    {
        $pattern = '/(a)(b)\1\2/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'abab');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abba');
    }

    public function testBackreferencesReverseOrder(): void
    {
        $pattern = '/(a)(b)(c)\3\2\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'abccba');
    }

    public function testMixedOrderBackreferences(): void
    {
        $pattern = '/(a)(b)\2(c)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'abbca');
    }

    public function testBackreferenceToNestedQuantifier(): void
    {
        $pattern = '/((?:a+)+)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
    }

    public function testBackreferenceInsideQuantifier(): void
    {
        $pattern = '/(?:(a)\1)+/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'aaaa');
    }

    public function testNestedGroupsWithMultipleBackreferences(): void
    {
        $pattern = '/((a))\1\2/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'aaaa');
    }

    public function testComplexBackreferencePattern(): void
    {
        $pattern = '/(test)(\w+)\2\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
    }

    public function testBackreferenceAnyChar(): void
    {
        $pattern = '/(.)\\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'aa');
        $this->assertMatchesRegularExpression($pattern, '##');
    }

    public function testBackreferenceWithCharClassAndQuantifier(): void
    {
        $pattern = '/([a-z]+)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'testtest');
        $this->assertDoesNotMatchRegularExpression($pattern, 'testword');
    }

    public function testBackreferenceToWord(): void
    {
        $pattern = '/(test)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'testtest');
        $this->assertDoesNotMatchRegularExpression($pattern, 'testing');
    }

    public function testAlternationWithBackreferences(): void
    {
        $pattern = '/(a|b)(a|b)\1\2/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
    }

    public function testComplexMixedBackreferences(): void
    {
        $pattern = '/(a)(b)\2(c)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'abbca');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abcba');
    }

    public function testTwoDigitBackreference(): void
    {
        $pattern = '/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)\10/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
    }

    public function testBackreferenceWithAlternationInGroup(): void
    {
        $pattern = '/(a|b|c)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
    }

    public function testMultipleConsecutiveBackreferences(): void
    {
        $pattern = '/(x)(y)(z)\1\2\3/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'xyzxyz');
    }

    public function testBackreferenceWithDigitClass(): void
    {
        $pattern = '/(\d+)\1/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, '123123');
    }

    public function testBackreferenceInComplexPattern(): void
    {
        $pattern = '/^(a+)b\1$/';
        $compiled = $this->roundTrip($pattern);
        
        $this->assertSame($pattern, $compiled);
        
        $this->assertMatchesRegularExpression($pattern, 'aabaa');
    }
}
