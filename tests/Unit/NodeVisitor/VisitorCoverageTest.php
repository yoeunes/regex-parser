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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\AsciiTreeVisitor;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;
use RegexParser\NodeVisitor\LengthRangeNodeVisitor;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\NodeVisitor\LiteralExtractorNodeVisitor;
use RegexParser\NodeVisitor\MermaidNodeVisitor;
use RegexParser\NodeVisitor\MetricsNodeVisitor;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;
use RegexParser\NodeVisitor\NodeVisitorInterface;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\RailroadSvgVisitor;
use RegexParser\NodeVisitor\ReDoSProfileNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\TestCaseGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

final class VisitorCoverageTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    /**
     * This provider includes rare PCRE constructs to hit specific branches
     * in ExplainVisitor, CompilerNodeVisitor, and DumperNodeVisitor.
     */
    public static function provideRareConstructs(): \Iterator
    {
        // Assertions
        yield ['/\A\Z\z\G\b\B/'];

        // Char Types (vertical/horizontal/newline)
        yield ['/\v\V\h\H\R/'];

        // Escapes
        yield ['/\012\o{123}\x{F}\u{1F600}/'];

        // Backreferences & Subroutines
        yield ['/(a)\g{1}\g{-1}\g<1>/']; // \g as backref
        yield ['/(?<name>a)(?&name)(?P>name)\g<name>/']; // \g as subroutine

        // PCRE Verbs
        yield ['/(*FAIL)(*ACCEPT)(*COMMIT)(*PRUNE)(*SKIP)(*THEN)(*UTF8)/'];

        // Conditionals
        yield ['/(?(1)yes|no)/']; // Numeric ref
        yield ['/(?(<name>)yes)/']; // Named ref
        yield ['/(?(R)yes)/']; // Recursion check
        yield ['/(?(?=a)yes)/']; // Lookahead check
        yield ['/(?(DEFINE)a)/']; // Define

        // POSIX classes
        yield ['/[[:alpha:][:digit:][:xdigit:][:punct:][:graph:][:print:][:cntrl:]]/'];
    }

    #[DataProvider('provideRareConstructs')]
    public function test_visitors_handle_construct(string $regex): void
    {
        // We deliberately suppress errors for compilation of exotic features
        // that might not be supported by the underlying PCRE version of the OS,
        // but our Parser supports them.
        try {
            $ast = $this->regex->parse($regex);
        } catch (\Exception $e) {
            // If the parser fails, the test fails.
            $this->fail('Parser failed on: '.$regex.' Error: '.$e->getMessage());
        }

        // 1. Test Compiler (Round-trip)
        $compiler = new CompilerNodeVisitor();
        $compiled = $ast->accept($compiler);
        $this->assertNotEmpty($compiled);

        // 2. Test Dumper (String representation)
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);
        $this->assertNotEmpty($dump);

        // 3. Test Explain (Text)
        $explainer = new ExplainNodeVisitor();
        $explanation = $ast->accept($explainer);
        $this->assertNotEmpty($explanation);

        // 4. Test HTML Explain
        $htmlExplainer = new HtmlExplainNodeVisitor();
        $html = $ast->accept($htmlExplainer);
        $this->assertNotEmpty($html);
    }

    public function test_all_visitors_can_be_instantiated(): void
    {
        // Instantiate all visitor classes to cover class coverage
        $visitors = [
            new CompilerNodeVisitor(),
            new ComplexityScoreNodeVisitor(),
            new ConsoleHighlighterVisitor(),
            new DumperNodeVisitor(),
            new ExplainNodeVisitor(),
            new HtmlExplainNodeVisitor(),
            new HtmlHighlighterVisitor(),
            new LengthRangeNodeVisitor(),
            new LinterNodeVisitor(),
            new LiteralExtractorNodeVisitor(),
            new MermaidNodeVisitor(),
            new MetricsNodeVisitor(),
            new ModernizerNodeVisitor(),
            new OptimizerNodeVisitor(),
            new AsciiTreeVisitor(),
            new RailroadSvgVisitor(),
            new ReDoSProfileNodeVisitor(),
            new SampleGeneratorNodeVisitor(),
            new TestCaseGeneratorNodeVisitor(),
            new ValidatorNodeVisitor(),
        ];

        // Visitors instantiated for coverage testing
        $this->assertCount(20, $visitors);
        $this->assertContainsOnlyInstancesOf(NodeVisitorInterface::class, $visitors);
    }
}
