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

namespace RegexParser;

use RegexParser\Builder\RegexBuilder;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\LiteralExtractorVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

/**
 * Main service for parsing, validating, and manipulating regex patterns.
 * This class is intended to be instantiated via a DI container.
 */
class Regex
{
    /**
     * @param Parser                 $parser    the configured parser instance
     * @param ValidatorNodeVisitor   $validator a reusable validator visitor
     * @param ExplainVisitor         $explainer a reusable explain visitor
     * @param SampleGeneratorVisitor $generator a reusable sample generator visitor
     * @param OptimizerNodeVisitor   $optimizer a reusable optimizer visitor
     * @param DumperNodeVisitor      $dumper    a reusable dumper visitor
     * @param ComplexityScoreVisitor $scorer    a reusable complexity scorer
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly ValidatorNodeVisitor $validator,
        private readonly ExplainVisitor $explainer,
        private readonly SampleGeneratorVisitor $generator,
        private readonly OptimizerNodeVisitor $optimizer,
        private readonly DumperNodeVisitor $dumper,
        private readonly ComplexityScoreVisitor $scorer,
    ) {}

    /**
     * Static constructor for easy use without a DI container.
     *
     * @param array{
     *     max_pattern_length?: int,
     * } $options Options for the parser (e.g., 'max_pattern_length').
     */
    public static function create(array $options = []): self
    {
        return new self(
            new Parser($options),
            new ValidatorNodeVisitor(),
            new ExplainVisitor(),
            new SampleGeneratorVisitor(),
            new OptimizerNodeVisitor(),
            new DumperNodeVisitor(),
            new ComplexityScoreVisitor(),
        );
    }

    /**
     * Parses a full PCRE regex string into an Abstract Syntax Tree.
     *
     * @throws LexerException|ParserException
     */
    public function parse(string $regex): RegexNode
    {
        return $this->parser->parse($regex);
    }

    /**
     * Validates the syntax and semantics (e.g., ReDoS, valid backrefs) of a regex.
     */
    public function validate(string $regex): ValidationResult
    {
        try {
            $ast = $this->parser->parse($regex);
            // We must use a fresh visitor instance for each run to reset internal state.
            $validator = clone $this->validator;
            $ast->accept($validator);

            // Validation passed, now get the score
            $scorer = clone $this->scorer;
            $score = $ast->accept($scorer);

            return new ValidationResult(true, null, $score);
        } catch (LexerException|ParserException $e) {
            return new ValidationResult(false, $e->getMessage());
        }
    }

    /**
     * Explains the regex in a human-readable format.
     *
     * @throws LexerException|ParserException
     */
    public function explain(string $regex): string
    {
        $ast = $this->parser->parse($regex);

        return $ast->accept(clone $this->explainer);
    }

    /**
     * Generates a random sample string that matches the regex.
     *
     * @throws LexerException|ParserException
     */
    public function generate(string $regex): string
    {
        $ast = $this->parser->parse($regex);

        return $ast->accept(clone $this->generator);
    }

    /**
     * Optimizes the regex AST and returns the simplified regex string.
     *
     * @throws LexerException|ParserException
     */
    public function optimize(string $regex): string
    {
        $ast = $this->parser->parse($regex);

        // 1. Optimize the AST (AST -> AST)
        $optimizedAst = $ast->accept(clone $this->optimizer);

        // 2. Compile the new AST to a string (AST -> string)
        $compiler = new CompilerNodeVisitor();

        return $optimizedAst->accept($compiler);
    }

    /**
     * Dumps the AST as a string for debugging.
     *
     * @throws LexerException|ParserException
     */
    public function dump(string $regex): string
    {
        $ast = $this->parser->parse($regex);

        return $ast->accept(clone $this->dumper);
    }

    /**
     * Extracts literal strings that must appear in any match.
     * useful for pre-match optimizations (e.g. strpos check).
     *
     * * @throws LexerException|ParserException
     */
    public function extractLiterals(string $regex): LiteralSet
    {
        $ast = $this->parser->parse($regex);

        // Use a fresh visitor instance
        $visitor = new LiteralExtractorVisitor();

        return $ast->accept($visitor);
    }

    /**
     * Performs a detailed ReDoS vulnerability analysis.
     * Returns a report with severity, score, and recommendations.
     */
    public function analyzeReDoS(string $regex): ReDoSAnalysis
    {
        // We can reuse the internal parser
        $analyzer = new ReDoSAnalyzer($this->parser);

        return $analyzer->analyze($regex);
    }

    /**
     * Returns a fluent builder to construct regex programmatically.
     */
    public static function builder(): RegexBuilder
    {
        return RegexBuilder::create();
    }
}
