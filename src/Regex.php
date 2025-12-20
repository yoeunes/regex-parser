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

use RegexParser\Cache\CacheInterface;
use RegexParser\Cache\NullCache;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Exception\ResourceLimitException;
use RegexParser\Internal\PatternParser;
use RegexParser\Node\RegexNode;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSAnalyzer;
use RegexParser\ReDoS\ReDoSSeverity;

/**
 * Main entry point for the RegexParser library.
 *
 * Provides an API for parsing, validating, optimizing, and analyzing
 * regular expressions with comprehensive error handling and caching support.
 */
final readonly class Regex
{
    /**
     * Default maximum allowed regex pattern length.
     */
    public const DEFAULT_MAX_PATTERN_LENGTH = 100_000;

    /**
     * Default maximum allowed lookbehind length.
     */
    public const DEFAULT_MAX_LOOKBEHIND_LENGTH = 255;

    /**
     * Create a new Regex instance with the specified configuration.
     *
     * @param int            $maxPatternLength     Maximum allowed pattern length
     * @param int            $maxLookbehindLength  Maximum allowed lookbehind length
     * @param CacheInterface $cache                Cache implementation for parsed patterns
     * @param array<string>  $redosIgnoredPatterns Patterns to ignore in ReDoS analysis
     */
    private function __construct(
        private int $maxPatternLength,
        private int $maxLookbehindLength,
        private CacheInterface $cache,
        private array $redosIgnoredPatterns,
    ) {}

    /**
     * Create a new Regex instance with optional configuration.
     *
     * @param array<string, mixed> $options Configuration options
     *
     * @return self New Regex instance
     */
    public static function create(array $options = []): self
    {
        $configuration = RegexOptions::fromArray($options);

        return new self(
            $configuration->maxPatternLength,
            $configuration->maxLookbehindLength,
            $configuration->cache,
            $configuration->redosIgnoredPatterns,
        );
    }

    /**
     * Parse a regular expression into an Abstract Syntax Tree (AST).
     *
     * @param string $regex    The regular expression to parse
     * @param bool   $tolerant Whether to return a tolerant result on parse errors
     *
     * @return ($tolerant is true ? TolerantParseResult : RegexNode) Parsed AST or tolerant result
     */
    public function parse(string $regex, bool $tolerant = false): RegexNode|TolerantParseResult
    {
        try {
            $ast = $this->doParse($regex);

            return $tolerant ? new TolerantParseResult($ast) : $ast;
        } catch (LexerException|ParserException $parseException) {
            if (!$tolerant) {
                throw $parseException;
            }

            $fallbackAst = $this->buildFallbackAstFromException($parseException, $regex);

            return new TolerantParseResult($fallbackAst, [$parseException]);
        }
    }

    /**
     * Validate a regular expression and return detailed validation results.
     *
     * @param string $regex The regular expression to validate
     *
     * @return ValidationResult Detailed validation result
     */
    public function validate(string $regex): ValidationResult
    {
        try {
            $extractedPattern = $this->extractPatternSafely($regex);
            $ast = $this->parse($regex, false);

            $this->validateAst($ast, $extractedPattern);
            $complexityScore = $this->calculateComplexity($ast);

            return new ValidationResult(true, null, $complexityScore);
        } catch (LexerException|ParserException $parseException) {
            return $this->buildValidationFailure($parseException);
        }
    }

    /**
     * Analyze a regular expression for potential ReDoS (Regular Expression Denial of Service) vulnerabilities.
     *
     * @param string             $regex     The regular expression to analyze
     * @param ReDoSSeverity|null $threshold Minimum severity level to report
     *
     * @return ReDoSAnalysis Detailed ReDoS analysis results
     */
    public function redos(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        $analyzer = new ReDoSAnalyzer($this, array_values($this->redosIgnoredPatterns));

        return $analyzer->analyze($regex, $threshold);
    }

    /**
     * Extract literal strings from a regular expression pattern.
     *
     * @param string $regex The regular expression to analyze
     *
     * @return LiteralExtractionResult Extracted literals and search patterns
     */
    public function literals(string $regex): LiteralExtractionResult
    {
        $ast = $this->parse($regex, false);

        $literalSet = $ast->accept(new NodeVisitor\LiteralExtractorNodeVisitor());

        $uniqueLiterals = $this->extractUniqueLiterals($literalSet);
        $searchPatterns = $this->buildSearchPatterns($literalSet);
        $confidenceLevel = $this->determineConfidenceLevel($literalSet);

        return new LiteralExtractionResult($uniqueLiterals, $searchPatterns, $confidenceLevel, $literalSet);
    }

    /**
     * Optimize a regular expression for better performance.
     *
     * @param string                                                 $regex   The regular expression to optimize
     * @param array{digits?: bool, word?: bool, strictRanges?: bool} $options Optimization options
     *
     * @return OptimizationResult Optimization results with changes applied
     */
    public function optimize(string $regex, array $options = []): OptimizationResult
    {
        $optimizer = new NodeVisitor\OptimizerNodeVisitor(
            optimizeDigits: (bool) ($options['digits'] ?? true),
            optimizeWord: (bool) ($options['word'] ?? true),
            strictRanges: (bool) ($options['strictRanges'] ?? true),
        );
        $optimizedPattern = $this->compile($regex, $optimizer);
        $appliedChanges = $optimizedPattern === $regex ? [] : ['Optimized pattern.'];

        return new OptimizationResult($regex, $optimizedPattern, $appliedChanges);
    }

    /**
     * Generate a sample string that matches the regular expression.
     *
     * @param string $regex The regular expression to generate a sample for
     *
     * @return string Generated sample string
     */
    public function generate(string $regex): string
    {
        $ast = $this->parse($regex, false);

        return $ast->accept(new NodeVisitor\SampleGeneratorNodeVisitor());
    }

    /**
     * Generate a human-readable explanation of the regular expression.
     *
     * @param string $regex  The regular expression to explain
     * @param string $format Output format ('text' or 'html')
     *
     * @return string Formatted explanation
     */
    public function explain(string $regex, string $format = 'text'): string
    {
        $explanationVisitor = $this->createExplanationVisitor($format);

        $ast = $this->parse($regex, false);

        return $ast->accept($explanationVisitor);
    }

    /**
     * Compile a regex by applying a transformation and compiling back to string.
     *
     * @param string                                               $regex       The regular expression to compile
     * @param NodeVisitor\NodeVisitorInterface<Node\NodeInterface> $transformer The transformation to apply
     *
     * @return string Compiled regex string
     */
    private function compile(string $regex, NodeVisitor\NodeVisitorInterface $transformer): string
    {
        $ast = $this->parse($regex, false);

        $transformed = $ast->accept($transformer);

        return $transformed->accept(new NodeVisitor\CompilerNodeVisitor());
    }

    /**
     * Attempt to load a parsed regex from cache.
     *
     * @param string $regex The regex pattern to look up
     *
     * @return array{0: RegexNode|null, 1: string|null} Cached AST and cache key
     */
    private function loadFromCache(string $regex): array
    {
        if ($this->cache instanceof NullCache) {
            return [null, null];
        }

        $cacheKey = $this->cache->generateKey($regex);
        $cachedResult = $this->cache->load($cacheKey);

        return [$cachedResult instanceof RegexNode ? $cachedResult : null, $cacheKey];
    }

    /**
     * Store a parsed regex AST in cache.
     *
     * @param string|null $cacheKey The cache key to store under
     * @param RegexNode   $ast      The AST to cache
     */
    private function storeInCache(?string $cacheKey, RegexNode $ast): void
    {
        if (null === $cacheKey) {
            return;
        }

        try {
            $this->cache->write($cacheKey, self::prepareCachePayload($ast));
        } catch (\Throwable) {
            // Cache failures are silently ignored
        }
    }

    /**
     * Prepare AST for cache storage by serializing it.
     *
     * @param RegexNode $ast The AST to serialize
     *
     * @return string Serialized PHP code
     */
    private static function prepareCachePayload(RegexNode $ast): string
    {
        $serializedAst = serialize($ast);
        $exportedAst = var_export($serializedAst, true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            return unserialize($exportedAst, ['allowed_classes' => true]);

            PHP;
    }

    /**
     * Safely extract pattern components from a regex string.
     *
     * @param string $regex The regex to extract from
     *
     * @return string|null Extracted pattern or null on failure
     */
    private function extractPatternSafely(string $regex): ?string
    {
        try {
            [$pattern] = PatternParser::extractPatternAndFlags($regex);

            return (string) $pattern;
        } catch (ParserException) {
            return null;
        }
    }

    /**
     * Validate an AST with the appropriate validators.
     *
     * @param RegexNode   $ast     The AST to validate
     * @param string|null $pattern The original pattern for context
     */
    private function validateAst(RegexNode $ast, ?string $pattern): void
    {
        $validator = new NodeVisitor\ValidatorNodeVisitor($this->maxLookbehindLength, $pattern);
        $ast->accept($validator);
    }

    /**
     * Calculate complexity score for an AST.
     *
     * @param RegexNode $ast The AST to score
     *
     * @return int Complexity score
     */
    private function calculateComplexity(RegexNode $ast): int
    {
        $scorer = new NodeVisitor\ComplexityScoreNodeVisitor();

        return $ast->accept($scorer);
    }

    /**
     * Build a validation failure result from an exception.
     *
     * @param LexerException|ParserException $exception The parse exception
     *
     * @return ValidationResult Validation failure result
     */
    private function buildValidationFailure(LexerException|ParserException $exception): ValidationResult
    {
        $errorMessage = $exception->getMessage();
        $visualSnippet = $exception->getVisualSnippet();

        if ('' !== $visualSnippet) {
            $errorMessage .= "\n".$visualSnippet;
        }

        if ($exception instanceof Exception\SemanticErrorException) {
            return new ValidationResult(
                false,
                $errorMessage,
                0,
                ValidationErrorCategory::SEMANTIC,
                $exception->getPosition(),
                '' !== $visualSnippet ? $visualSnippet : null,
                $exception->getHint(),
                $exception->getErrorCode(),
            );
        }

        return new ValidationResult(
            false,
            $errorMessage,
            0,
            ValidationErrorCategory::SYNTAX,
            $exception->getPosition(),
            '' !== $visualSnippet ? $visualSnippet : null,
            null,
            null,
        );
    }

    /**
     * Build a fallback AST when parsing fails.
     *
     * @param LexerException|ParserException $exception The parse exception
     * @param string                         $regex     The original regex
     *
     * @return RegexNode Fallback AST
     */
    private function buildFallbackAstFromException(LexerException|ParserException $exception, string $regex): RegexNode
    {
        [$pattern, $flags, $delimiter, $length] = $this->safeExtractPattern($regex);

        return $this->buildFallbackAst($pattern, $flags, $delimiter, $length, $exception->getPosition());
    }

    /**
     * Extract unique literals from a literal set.
     *
     * @param mixed $literalSet The literal set from extraction
     *
     * @return list<string> Unique literals
     */
    private function extractUniqueLiterals(mixed $literalSet): array
    {
        if (!\is_object($literalSet)) {
            return [];
        }

        /** @var array<string> $prefixes */
        $prefixes = property_exists($literalSet, 'prefixes') ? $literalSet->prefixes : [];

        /** @var array<string> $suffixes */
        $suffixes = property_exists($literalSet, 'suffixes') ? $literalSet->suffixes : [];

        return array_values(array_unique(array_merge($prefixes, $suffixes)));
    }

    /**
     * Build search patterns from prefixes and suffixes.
     *
     * @param mixed $literalSet The literal set containing prefixes/suffixes
     *
     * @return list<string> Search patterns
     */
    private function buildSearchPatterns(mixed $literalSet): array
    {
        $patterns = [];

        if (\is_object($literalSet) && property_exists($literalSet, 'prefixes')) {
            /** @var array<string> $prefixes */
            $prefixes = $literalSet->prefixes;

            foreach ($prefixes as $prefix) {
                if (\is_string($prefix) && '' !== $prefix) {
                    $patterns[] = '^'.preg_quote($prefix, '/');
                }
            }
        }

        if (\is_object($literalSet) && property_exists($literalSet, 'suffixes')) {
            /** @var array<string> $suffixes */
            $suffixes = $literalSet->suffixes;

            foreach ($suffixes as $suffix) {
                if (\is_string($suffix) && '' !== $suffix) {
                    $patterns[] = preg_quote($suffix, '/').'$';
                }
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Determine confidence level for literal extraction.
     *
     * @param mixed $literalSet The literal set to evaluate
     *
     * @return string Confidence level ('high', 'medium', or 'low')
     */
    private function determineConfidenceLevel(mixed $literalSet): string
    {
        if (!\is_object($literalSet)) {
            return 'low';
        }

        $isComplete = property_exists($literalSet, 'complete') ? $literalSet->complete : false;
        $isVoid = (method_exists($literalSet, 'isVoid') && $literalSet->isVoid()) ? true : false;

        if ($isComplete && !$isVoid) {
            return 'high';
        }

        if (!$isVoid) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Create appropriate explanation visitor based on format.
     *
     * @param string $format The desired output format
     *
     * @return NodeVisitor\ExplainNodeVisitor|NodeVisitor\HtmlExplainNodeVisitor The explanation visitor
     */
    private function createExplanationVisitor(string $format): NodeVisitor\ExplainNodeVisitor|NodeVisitor\HtmlExplainNodeVisitor
    {
        return match ($format) {
            'text' => new NodeVisitor\ExplainNodeVisitor(),
            'html' => new NodeVisitor\HtmlExplainNodeVisitor(),
            default => throw new \InvalidArgumentException("Invalid format: $format"),
        };
    }

    /**
     * Safely extract pattern components with error handling.
     *
     * @return array{0: string, 1: string, 2: string, 3: int} Pattern components
     */
    private function safeExtractPattern(string $regex): array
    {
        try {
            [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);
            $pattern = (string) $pattern;
            $flags = (string) $flags;
            $delimiter = (string) $delimiter;
            $patternLength = \strlen($pattern);

            return [$pattern, $flags, $delimiter, $patternLength];
        } catch (ParserException) {
            return [$regex, '', '/', \strlen($regex)];
        }
    }

    /**
     * Build a fallback AST for partial parsing.
     *
     * @param string   $pattern       The pattern string
     * @param string   $flags         Regex flags
     * @param string   $delimiter     Pattern delimiter
     * @param int      $patternLength Length of the pattern
     * @param int|null $errorPosition Position where error occurred
     *
     * @return Node\RegexNode Fallback AST
     */
    private function buildFallbackAst(
        string $pattern,
        string $flags,
        string $delimiter,
        int $patternLength,
        ?int $errorPosition
    ): Node\RegexNode {
        $validPattern = null === $errorPosition
            ? $pattern
            : substr($pattern, 0, max(0, $errorPosition));

        $literalNode = new Node\LiteralNode($validPattern, 0, \strlen($validPattern));
        $sequenceNode = new Node\SequenceNode([$literalNode], 0, $literalNode->getEndPosition());

        return new Node\RegexNode($sequenceNode, $flags, $delimiter, 0, $patternLength);
    }

    /**
     * Perform the actual parsing with caching and resource limits.
     *
     * @param string $regex The regex to parse
     *
     * @return RegexNode The parsed AST
     */
    private function doParse(string $regex): RegexNode
    {
        $this->validateResourceLimits($regex);

        [$cachedAst, $cacheKey] = $this->loadFromCache($regex);
        if (null !== $cachedAst) {
            return $cachedAst;
        }

        $ast = $this->parseFromScratch($regex);
        $this->storeInCache($cacheKey, $ast);

        return $ast;
    }

    /**
     * Validate resource limits for the regex pattern.
     *
     * @param string $regex The regex to validate
     */
    private function validateResourceLimits(string $regex): void
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw ResourceLimitException::withContext(
                \sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength),
                $this->maxPatternLength,
                $regex,
            );
        }
    }

    /**
     * Parse a regex from scratch without using cache.
     *
     * @param string $regex The regex to parse
     *
     * @return RegexNode The parsed AST
     */
    private function parseFromScratch(string $regex): RegexNode
    {
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);

        $tokenStream = (new Lexer())->tokenize($pattern);
        $parser = new Parser();

        return $parser->parse($tokenStream, $flags, $delimiter, \strlen($pattern));
    }
}
