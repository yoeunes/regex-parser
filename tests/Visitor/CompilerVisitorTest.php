<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\CompilerVisitor;

class CompilerVisitorTest extends TestCase
{
    private function compile(string $regex): string
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new CompilerVisitor();

        return $ast->accept($visitor);
    }

    public function testCompileSimple(): void
    {
        $this->assertSame('/foo/', $this->compile('/foo/'));
    }

    public function testCompileGroupAndAlternation(): void
    {
        $this->assertSame('/(foo|bar)?/', $this->compile('/(foo|bar)?/'));
    }

    public function testCompilePrecedence(): void
    {
        $this->assertSame('/ab*c/', $this->compile('/ab*c/'));
    }

    public function testCompileEscaped(): void
    {
        // The compiler must re-escape special characters
        $this->assertSame('/a\*c/', $this->compile('/a\*c/'));
    }

    public function testCompileNewNodesAndFlags(): void
    {
        $regex = '/^.\d\S(foo|bar)+$/imsU';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileQuantifiedSequence(): void
    {
        // This test ensures a *capturing* group remains a *capturing* group
        $this->assertSame('/(abc)+/', $this->compile('/(abc)+/'));
    }

    public function testCompileCharClass(): void
    {
        // Gère la négation, les ranges, les char types, et les littéraux (comme '-')
        $regex = '/[a-z\d-]/';
        $this->assertSame($regex, $this->compile($regex));

        $regex = '/[^a-z]/';
        $this->assertSame($regex, $this->compile($regex));

        // S'assure que les méta-caractères de classe sont échappés
        $regex = '/[]\^-]/'; // "]", "\", "^", "-"
        // The parser sees "]", "\", "^", "-" as literals because of their position.
        // The compiler should only escape the backslash.
        $this->assertSame('/[\\]^-]/', $this->compile($regex));
    }

    // Add new tests for new features
    public function testCompileNewFeatures(): void
    {
        $regex = '#(?<name>foo)+?|(?!=bar)#i';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileAssertion(): void
    {
        $regex = '/\Afoo\b/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileUnicodeProp(): void
    {
        $regex = '/\p{L}\P{^L}/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileOctal(): void
    {
        $regex = '/\o{777}/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileNamedBackref(): void
    {
        $regex = '/\k<name>/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileComment(): void
    {
        $regex = '/(?#test)/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileConditional(): void
    {
        $regex = '/(?(1)a|b)/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function testCompileInlineFlags(): void
    {
        $regex = '/(?i:foo)/';
        $this->assertSame($regex, $this->compile($regex));
    }
}
