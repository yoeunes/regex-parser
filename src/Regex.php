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

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\LiteralExtractorVisitor;
use RegexParser\NodeVisitor\MermaidVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSAnalyzer;

/**
 * Main service for parsing, validating, and manipulating regex patterns.
 *
 * This class provides a high-level API for common regex operations.
 * It uses RegexCompiler internally, which combines Lexer + Parser
 * for convenient string-based parsing with caching support.
 */
readonly class Regex
{
    /**
     * @param ValidatorNodeVisitor   $validator a reusable validator visitor
     * @param ExplainVisitor         $explainer a reusable explain visitor
     * @param SampleGeneratorVisitor $generator a reusable sample generator visitor
     * @param OptimizerNodeVisitor   $optimizer a reusable optimizer visitor
     * @param DumperNodeVisitor      $dumper    a reusable dumper visitor
     * @param ComplexityScoreVisitor $scorer    a reusable complexity scorer
     */
    public function __construct(
        private ValidatorNodeVisitor $validator,
        private ExplainVisitor $explainer,
        private SampleGeneratorVisitor $generator,
        private OptimizerNodeVisitor $optimizer,
        private DumperNodeVisitor $dumper,
        private ComplexityScoreVisitor $scorer,
    ) {}

    /**
     * Static constructor for easy use without a DI container.
     *
     * @param array{
     *     max_pattern_length?: int,
     *     max_recursion_depth?: int,
     *     max_nodes?: int,
     * } $options Options for the compiler (e.g., 'max_pattern_length', 'max_recursion_depth', 'max_nodes').
     */
    public static function create(array $options = []): self
    {
        return new self(
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
        return $this->getParser()->parse($regex);
    }

    /**
     * Validates the syntax and semantics (e.g., ReDoS, valid backrefs) of a regex.
     */
    public function validate(string $regex): ValidationResult
    {
        try {
            $ast = $this->getParser()->parse($regex);
            $validator = clone $this->validator;
            $ast->accept($validator);

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
        $ast = $this->getParser()->parse($regex);

        return $ast->accept(clone $this->explainer);
    }

    /**
     * Generates a random sample string that matches the regex.
     *
     * @throws LexerException|ParserException
     */
    public function generate(string $regex): string
    {
        $ast = $this->getParser()->parse($regex);

        return $ast->accept(clone $this->generator);
    }

    /**
     * Optimizes the regex AST and returns the simplified regex string.
     *
     * @throws LexerException|ParserException
     */
    public function optimize(string $regex): string
    {
        $ast = $this->getParser()->parse($regex);

        $optimizedAst = $ast->accept(clone $this->optimizer);

        $compiler = new CompilerNodeVisitor();

        return $optimizedAst->accept($compiler);
    }

    /**
     * Visualizes the regex AST as a Mermaid.js flowchart.
     *
     * Useful for debugging complex patterns and documentation.
     *
     * @throws LexerException|ParserException
     */
    public function visualize(string $regex): string
    {
        $ast = $this->getParser()->parse($regex);

        return $ast->accept(new MermaidVisitor());
    }

    /**
     * Dumps the AST as a string for debugging.
     *
     * @throws LexerException|ParserException
     */
    public function dump(string $regex): string
    {
        $ast = $this->getParser()->parse($regex);

        return $ast->accept(clone $this->dumper);
    }

    /**
     * Extracts literal strings that must appear in any match.
     * useful for pre-match optimizations (e.g. strpos check).
     *
     * @throws LexerException|ParserException
     */
    public function extractLiterals(string $regex): LiteralSet
    {
        $ast = $this->getParser()->parse($regex);

        $visitor = new LiteralExtractorVisitor();

        return $ast->accept($visitor);
    }

    /**
     * Performs a detailed ReDoS vulnerability analysis.
     * Returns a report with severity, score, and recommendations.
     */
    public function analyzeReDoS(string $regex): ReDoSAnalysis
    {
        $analyzer = new ReDoSAnalyzer($this->compiler);

        return $analyzer->analyze($regex);
    }

    /**
     * Returns the underlying Parser instance.
     * Useful for advanced scenarios requiring direct TokenStream parsing.
     */
    public function getParser(): Parser
    {
        return new Parser();
    }
}
