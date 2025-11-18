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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\Parser;

class ComplexityScoreVisitorTest extends TestCase
{
    private const int BASE_SCORE = 1;
    private const int COMPLEX_SCORE = 5;
    private const int UNBOUNDED_SCORE = 10;
    private const int RECURSIVE_SCORE = 10; // COMPLEX_SCORE * 2

    public function test_simple_regex_score(): void
    {
        // abc = 1 (seq) + 1 + 1 + 1 = 4 (or close, depends on your exact base logic)
        $score = $this->getScore('/abc/');
        $this->assertLessThan(10, $score);
    }

    public function test_high_complexity_score(): void
    {
        // Classic ReDoS: (a+)+
        // Your logic should multiply the score exponentially for nested quantifiers
        $score = $this->getScore('/(a+)+/');

        $this->assertGreaterThan(20, $score, 'Nested quantifiers should yield a high complexity score');
    }

    public function test_lookarounds_increase_score(): void
    {
        $simple = $this->getScore('/foo/');
        $complex = $this->getScore('/(?=foo)foo/');

        $this->assertGreaterThan($simple, $complex);
    }

    public static function data_provider_quantifier_explanations(): \Iterator
    {
        yield 'greedy_star' => ['/a*/', 'zero or more times'];
        yield 'lazy_plus' => ['/a+?/', 'one or more times (as few as possible)'];
        yield 'possessive_optional' => ['/a?+/', 'zero or one time (and do not backtrack)'];
        yield 'fixed_exact' => ['/a{5}/', 'exactly 5 times'];
        yield 'fixed_range_unbounded' => ['/a{5,}/', 'at least 5 times'];
        yield 'fixed_range_bounded_lazy' => ['/a{1,2}?/', 'between 1 and 2 times (as few as possible)'];
    }

    #[DataProvider('data_provider_quantifier_explanations')]
    public function test_quantifier_explanations(string $regex, string $expectedQuantifierText): void
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new ExplainVisitor();

        $output = $ast->accept($visitor);
        $this->assertStringContainsString($expectedQuantifierText, $output);
    }

    public function test_group_types_explanations(): void
    {
        $parser = new Parser();

        $this->assertStringContainsString(
            'Start Positive Lookbehind',
            $parser->parse('/(?<=a)/')->accept(new ExplainVisitor()),
        );
        $this->assertStringContainsString(
            'Start Atomic Group',
            $parser->parse('/(?>a)/')->accept(new ExplainVisitor()),
        );
        $this->assertStringContainsString(
            "Start Capturing Group (named: 'id')",
            $parser->parse('/(?<id>a)/')->accept(new ExplainVisitor()),
        );
    }

    public function test_literal_special_character_explanations(): void
    {
        $parser = new Parser();
        $visitor = new ExplainVisitor();

        $this->assertStringContainsString("Literal: ' ' (space)", $parser->parse('/ /')->accept($visitor));
        $this->assertStringContainsString("Literal: '\\t' (tab)", $parser->parse('/\t/')->accept($visitor));
        $this->assertStringContainsString("Literal: '\\n' (newline)", $parser->parse('/\n/')->accept($visitor));
        $this->assertStringContainsString('Literal: (non-printable char)', $parser->parse("/\x01/")->accept($visitor));
    }

    private function getScore(string $regex): int
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new ComplexityScoreVisitor();

        return $ast->accept($visitor);
    }
}
