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
        // The compiler must add a (?:) group
        $this->assertSame('/(?:abc)+/', $this->compile('/(abc)+/'));
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
        $this->assertSame('/[\]\^-]/', $this->compile('/[]\^-]/'));
    }

    // Add new tests for new features
    public function testCompileNewFeatures(): void
    {
        $regex = '#(?<name>foo)+?|(?!=bar)#i';
        $this->assertSame($regex, $this->compile($regex));
    }
}
