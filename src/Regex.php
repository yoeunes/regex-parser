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
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSAnalyzer;

/**
 * Main service for parsing, validating, and manipulating regex patterns.
 *
 * This class provides a high-level API for common regex operations.
 * It orchestrates the Lexer and Parser to provide convenient string-based
 * parsing while keeping those components decoupled from each other.
 */
readonly class Regex
{
    /**
     * Default hard limit on the regex string length to prevent excessive processing/memory usage.
     */
    public const int DEFAULT_MAX_PATTERN_LENGTH = 100_000;

    /**
     * @param NodeVisitor\ValidatorNodeVisitor       $validator        a reusable validator visitor
     * @param NodeVisitor\ExplainNodeVisitor         $explainer        a reusable explain visitor
     * @param NodeVisitor\SampleGeneratorNodeVisitor $generator        a reusable sample generator visitor
     * @param NodeVisitor\OptimizerNodeVisitor       $optimizer        a reusable optimizer visitor
     * @param NodeVisitor\DumperNodeVisitor          $dumper           a reusable dumper visitor
     * @param NodeVisitor\ComplexityScoreNodeVisitor $scorer           a reusable complexity scorer
     * @param int                                    $maxPatternLength maximum allowed pattern length
     */
    public function __construct(
        private NodeVisitor\ValidatorNodeVisitor $validator,
        private NodeVisitor\ExplainNodeVisitor $explainer,
        private NodeVisitor\SampleGeneratorNodeVisitor $generator,
        private NodeVisitor\OptimizerNodeVisitor $optimizer,
        private NodeVisitor\DumperNodeVisitor $dumper,
        private NodeVisitor\ComplexityScoreNodeVisitor $scorer,
        private int $maxPatternLength = self::DEFAULT_MAX_PATTERN_LENGTH,
    ) {}

    /**
     * Static constructor for easy use without a DI container.
     *
     * @param array{
     *     max_pattern_length?: int,
     * } $options Options (e.g., 'max_pattern_length').
     */
    public static function create(array $options = []): self
    {
        return new self(
            new NodeVisitor\ValidatorNodeVisitor(),
            new NodeVisitor\ExplainNodeVisitor(),
            new NodeVisitor\SampleGeneratorNodeVisitor(),
            new NodeVisitor\OptimizerNodeVisitor(),
            new NodeVisitor\DumperNodeVisitor(),
            new NodeVisitor\ComplexityScoreNodeVisitor(),
            (int) ($options['max_pattern_length'] ?? self::DEFAULT_MAX_PATTERN_LENGTH),
        );
    }

    /**
     * Parses a full PCRE regex string into an Abstract Syntax Tree.
     *
     * @throws LexerException|ParserException
     */
    public function parse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw new ParserException(\sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength));
        }

        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $stream = $this->createTokenStream($pattern);
        $parser = $this->getParser();

        return $parser->parse($stream, $flags, $delimiter, \strlen($pattern));
    }

    /**
     * Validates the syntax and semantics (e.g., ReDoS, valid backrefs) of a regex.
     */
    public function validate(string $regex): ValidationResult
    {
        try {
            $ast = $this->parse($regex);
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
        $ast = $this->parse($regex);

        return $ast->accept(clone $this->explainer);
    }

    /**
     * Generates a random sample string that matches the regex.
     *
     * @throws LexerException|ParserException
     */
    public function generate(string $regex): string
    {
        $ast = $this->parse($regex);

        return $ast->accept(clone $this->generator);
    }

    /**
     * Optimizes the regex AST and returns the simplified regex string.
     *
     * @throws LexerException|ParserException
     */
    public function optimize(string $regex): string
    {
        $ast = $this->parse($regex);

        $optimizedAst = $ast->accept(clone $this->optimizer);

        $compiler = new NodeVisitor\CompilerNodeVisitor();

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
        $ast = $this->parse($regex);

        return $ast->accept(new NodeVisitor\MermaidNodeVisitor());
    }

    /**
     * Dumps the AST as a string for debugging.
     *
     * @throws LexerException|ParserException
     */
    public function dump(string $regex): string
    {
        $ast = $this->parse($regex);

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
        $ast = $this->parse($regex);

        $visitor = new NodeVisitor\LiteralExtractorNodeVisitor();

        return $ast->accept($visitor);
    }

    /**
     * Performs a detailed ReDoS vulnerability analysis.
     * Returns a report with severity, score, and recommendations.
     */
    public function analyzeReDoS(string $regex): ReDoSAnalysis
    {
        return new ReDoSAnalyzer()->analyze($regex);
    }

    /**
     * Returns the underlying Parser instance.
     * Useful for advanced scenarios requiring direct TokenStream parsing.
     */
    public function getParser(): Parser
    {
        return new Parser();
    }

    /**
     * Returns a new Lexer instance for the given pattern.
     */
    public function getLexer(string $pattern): Lexer
    {
        return new Lexer($pattern);
    }

    /**
     * Creates a TokenStream from a pattern string.
     *
     * @param string $pattern The regex pattern (without delimiters)
     */
    public function createTokenStream(string $pattern): TokenStream
    {
        $lexer = $this->getLexer($pattern);

        return new TokenStream($lexer->tokenize());
    }

    /**
     * Extracts pattern, flags, and delimiter from a full regex string.
     * Handles escaped delimiters correctly (e.g., "/abc\/def/i").
     *
     * @throws ParserException
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     */
    public function extractPatternAndFlags(string $regex): array
    {
        $len = \strlen($regex);
        if ($len < 2) {
            throw new ParserException('Regex is too short. It must include delimiters.');
        }

        $delimiter = $regex[0];
        // Handle bracket delimiters style: (pattern), [pattern], {pattern}, <pattern>
        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // Find the last occurrence of the closing delimiter that is NOT escaped
        // We scan from the end to optimize for flags
        for ($i = $len - 1; $i > 0; $i--) {
            if ($regex[$i] === $closingDelimiter) {
                // Check if escaped (count odd number of backslashes before it)
                $escapes = 0;
                for ($j = $i - 1; $j > 0 && '\\' === $regex[$j]; $j--) {
                    $escapes++;
                }

                if (0 === $escapes % 2) {
                    // Found the end delimiter
                    $pattern = substr($regex, 1, $i - 1);
                    $flags = substr($regex, $i + 1);

                    // Validate flags (only allow standard PCRE flags)
                    // n = NO_AUTO_CAPTURE, r = PCRE2_EXTRA_CASELESS_RESTRICT (unicode restricted)
                    if (!preg_match('/^[imsxADSUXJunr]*$/', $flags)) {
                        // Find the invalid flag for a better error message
                        $invalid = preg_replace('/[imsxADSUXJunr]/', '', $flags);

                        throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $invalid ?? $flags));
                    }

                    return [$pattern, $flags, $delimiter];
                }
            }
        }

        throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
    }
}
