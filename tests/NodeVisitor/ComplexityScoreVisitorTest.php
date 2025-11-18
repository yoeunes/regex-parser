<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\Parser;

class ComplexityScoreVisitorTest extends TestCase
{
    private function getScore(string $regex): int
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new ComplexityScoreVisitor();

        return $ast->accept($visitor);
    }

    public function testSimpleRegexScore(): void
    {
        // abc = 1 (seq) + 1 + 1 + 1 = 4 (or close, depends on your exact base logic)
        $score = $this->getScore('/abc/');
        $this->assertLessThan(10, $score);
    }

    public function testHighComplexityScore(): void
    {
        // Classic ReDoS: (a+)+
        // Your logic should multiply the score exponentially for nested quantifiers
        $score = $this->getScore('/(a+)+/');

        $this->assertGreaterThan(20, $score, 'Nested quantifiers should yield a high complexity score');
    }

    public function testLookaroundsIncreaseScore(): void
    {
        $simple = $this->getScore('/foo/');
        $complex = $this->getScore('/(?=foo)foo/');

        $this->assertGreaterThan($simple, $complex);
    }
}
