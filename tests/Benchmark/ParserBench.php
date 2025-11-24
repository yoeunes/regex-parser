<?php

declare(strict_types=1);

namespace RegexParser\Tests\Benchmark;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use RegexParser\Parser;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;

/**
 * Real performance benchmarks for RegexParser.
 *
 * Run with: vendor/bin/phpbench run
 */
#[Iterations(5)]
#[Revs(1000)]
class ParserBench
{
    private Parser $parser;

    private CompilerNodeVisitor $compiler;

    private ExplainVisitor $explainer;

    public function beforeMethods(): array
    {
        return ['setUp'];
    }

    public function setUp(): void
    {
        $this->parser = new Parser();
        $this->compiler = new CompilerNodeVisitor();
        $this->explainer = new ExplainVisitor();
    }

    /**
     * Benchmark: Parse simple pattern.
     */
    public function benchParseSimple(): void
    {
        $this->parser->parse('/test/');
    }

    /**
     * Benchmark: Parse pattern with character class.
     */
    public function benchParseCharClass(): void
    {
        $this->parser->parse('/[a-z0-9]+/i');
    }

    /**
     * Benchmark: Parse pattern with named groups.
     */
    public function benchParseNamedGroups(): void
    {
        $this->parser->parse('/(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/');
    }

    /**
     * Benchmark: Parse complex pattern.
     */
    public function benchParseComplex(): void
    {
        $this->parser->parse('/(?:https?:\/\/)(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/');
    }

    /**
     * Benchmark: Parse and compile.
     */
    public function benchParseAndCompile(): void
    {
        $ast = $this->parser->parse('/(?<email>\w+@\w+\.\w+)/');
        $ast->accept($this->compiler);
    }

    /**
     * Benchmark: Parse and explain.
     */
    public function benchParseAndExplain(): void
    {
        $ast = $this->parser->parse('/(?<email>\w+@\w+\.\w+)/');
        $ast->accept($this->explainer);
    }

    /**
     * Benchmark: Parse deeply nested groups.
     */
    public function benchParseDeepNesting(): void
    {
        $this->parser->parse('/((((((a))))))/');
    }

    /**
     * Benchmark: Parse many alternations.
     */
    public function benchParseManyAlternations(): void
    {
        $this->parser->parse('/(apple|banana|cherry|date|elderberry|fig|grape|honeydew|kiwi|lemon)/');
    }
}
