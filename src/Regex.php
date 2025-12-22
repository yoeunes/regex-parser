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
     * Cache version for AST serialization.
     * Bump this when AST structure changes.
     */
    public const CACHE_VERSION = 2;

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
        private bool $runtimePcreValidation,
    ) {}

    /**
     * Create a new Regex instance with optional configuration.
     *
     * Instances are not memoized; use Regex::new() for the same behavior.
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
            $configuration->runtimePcreValidation,
        );
    }

    /**
     * Parse a regular expression pattern with separate flags and delimiter.
     *
     * @param string $pattern   The regex pattern body
     * @param string $flags     The regex flags
     * @param string $delimiter The regex delimiter
     *
     * @return RegexNode Parsed AST
     */
    public function parsePattern(string $pattern, string $flags = '', string $delimiter = '/'): RegexNode
    {
        $regex = $delimiter.$pattern.$delimiter.$flags;

        return $this->parse($regex, false);
    }

    /**
     * Tokenize a regex into a token stream with positions.
     *
     * This exposes the same lexer the parser uses internally, including all
     * literal characters, whitespace, and comment markers, each tagged with
     * its byte offset in the pattern body. Combined with the delimiter and
     * flags extracted via PatternParser, this allows reconstructing the
     * original pattern and mapping nodes back to their exact locations.
     */
    public static function tokenize(string $regex): TokenStream
    {
        [$pattern] = Internal\PatternParser::extractPatternAndFlags($regex);

        return (new Lexer())->tokenize($pattern);
    }

    /**
     * Perform comprehensive analysis of a regex pattern.
     *
     * @param string $regex The regular expression to analyze
     *
     * @return AnalysisReport Complete analysis report
     */
    public function analyze(string $regex): AnalysisReport
    {
        $errors = [];
        $isValid = true;

        $validation = $this->validate($regex);
        if (!$validation->isValid) {
            $isValid = false;
            if (null !== $validation->error && '' !== $validation->error) {
                $errors[] = $validation->error;
            }
        }

        $lintIssues = [];
        $highlighted = '';
        $explain = '';
        $optimizations = new OptimizationResult($regex, $regex, []);

        $redos = $this->redos($regex);

        if ($isValid) {
            try {
                $ast = $this->parse($regex, false);
                $linter = new NodeVisitor\LinterNodeVisitor();
                $ast->accept($linter);
                $lintIssues = $linter->getIssues();
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $isValid = false;
            }

            try {
                $optimizations = $this->optimize($regex);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $isValid = false;
            }

            try {
                $explain = $this->explain($regex);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $isValid = false;
            }

            try {
                $highlighted = $this->highlight($regex);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $isValid = false;
            }
        }

        return new AnalysisReport(
            $isValid,
            $errors,
            $lintIssues,
            $redos,
            $optimizations,
            $explain,
            $highlighted,
        );
    }

    /**
     * Create a new Regex instance without memoization.
     *
     * @param array<string, mixed> $options Configuration options
     *
     * @return self New Regex instance
     */
    public static function new(array $options = []): self
    {
        return self::create($options);
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

            if ($this->runtimePcreValidation) {
                $runtimeResult = $this->checkRuntimeCompilation($regex, $extractedPattern, $complexityScore);
                if (null !== $runtimeResult) {
                    return $runtimeResult;
                }
            }

            return new ValidationResult(true, null, $complexityScore);
        } catch (\Throwable $e) {
            return $this->buildValidationFailure($e);
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
     * Highlight a regex for console or HTML output.
     *
     * @param string $regex  The regular expression to highlight
     * @param string $format Output format ('console' or 'html')
     */
    public function highlight(string $regex, string $format = 'console'): string
    {
        $ast = $this->parse($regex, false);

        $visitor = 'html' === $format
            ? new NodeVisitor\HtmlHighlighterVisitor()
            : new NodeVisitor\ConsoleHighlighterVisitor();

        return $ast->accept($visitor);
    }

    /**
     * Get the list of allowed classes for unserialization.
     *
     * @return list<class-string>
     */
    private static function getAllowedClasses(): array
    {
        return [
            // Node classes
            Node\RegexNode::class,
            Node\AlternationNode::class,
            Node\AnchorNode::class,
            Node\AssertionNode::class,
            Node\BackrefNode::class,
            Node\CalloutNode::class,
            Node\CharClassNode::class,
            Node\CharLiteralNode::class,
            Node\CharTypeNode::class,
            Node\ClassOperationNode::class,
            Node\CommentNode::class,
            Node\ConditionalNode::class,
            Node\ControlCharNode::class,
            Node\DefineNode::class,
            Node\DotNode::class,
            Node\GroupNode::class,
            Node\KeepNode::class,
            Node\LimitMatchNode::class,
            Node\LiteralNode::class,
            Node\PcreVerbNode::class,
            Node\PosixClassNode::class,
            Node\QuantifierNode::class,
            Node\RangeNode::class,
            Node\ScriptRunNode::class,
            Node\SequenceNode::class,
            Node\SubroutineNode::class,
            Node\UnicodeNode::class,
            Node\UnicodePropNode::class,
            Node\VersionConditionNode::class,
        ];
    }

    /**
     * Checks runtime compilation by attempting to use the pattern with preg_match and capturing warnings.
     */
    private function checkRuntimeCompilation(
        string $regex,
        ?string $pattern,
        int $complexityScore,
    ): ?ValidationResult {
        $warning = null;

        set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            if (\E_WARNING === $errno) {
                $warning = $errstr;
            }

            return true;
        });

        try {
            $result = preg_match($regex, '');
        } finally {
            restore_error_handler();
        }

        if (false !== $result && null === $warning) {
            return null;
        }

        $message = $this->normalizeRuntimeErrorMessage($warning ?? preg_last_error_msg());
        if ('' === $message || 'No error' === $message) {
            $message = 'PCRE runtime error.';
        }

        $offset = $this->extractOffsetFromMessage($message);
        $snippet = $this->buildVisualSnippet($pattern, $offset);
        $fullMessage = 'PCRE runtime error: '.$message;
        if ('' !== $snippet) {
            $fullMessage .= "\n".$snippet;
        }

        return new ValidationResult(
            false,
            $fullMessage,
            $complexityScore,
            ValidationErrorCategory::PCRE_RUNTIME,
            $offset,
            '' !== $snippet ? $snippet : null,
            null,
            'regex.pcre.runtime',
        );
    }

    private function normalizeRuntimeErrorMessage(string $message): string
    {
        $normalized = preg_replace('/^preg_[a-z_]+\\(\\):\\s*/i', '', $message) ?? $message;

        return trim($normalized);
    }

    private function extractOffsetFromMessage(string $message): ?int
    {
        if (preg_match('/\\b(?:at offset|offset)\\s+(\\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function buildVisualSnippet(?string $pattern, ?int $position): string
    {
        if (null === $pattern || null === $position || $position < 0) {
            return '';
        }

        $length = \strlen($pattern);
        $caretIndex = $position > $length ? $length : $position;

        $lineStart = strrpos($pattern, "\n", $caretIndex - $length);
        $lineStart = false === $lineStart ? 0 : $lineStart + 1;
        $lineEnd = strpos($pattern, "\n", $caretIndex);
        $lineEnd = false === $lineEnd ? $length : $lineEnd;

        $lineNumber = substr_count($pattern, "\n", 0, $lineStart) + 1;

        $displayStart = $lineStart;
        $displayEnd = $lineEnd;

        $maxContextWidth = 80;
        if (($displayEnd - $displayStart) > $maxContextWidth) {
            $half = intdiv($maxContextWidth, 2);
            $displayStart = max($lineStart, $caretIndex - $half);
            $displayEnd = min($lineEnd, $displayStart + $maxContextWidth);

            if (($displayEnd - $displayStart) > $maxContextWidth) {
                $displayStart = $displayEnd - $maxContextWidth;
            }
        }

        $prefixEllipsis = $displayStart > $lineStart ? '...' : '';
        $suffixEllipsis = $displayEnd < $lineEnd ? '...' : '';

        $excerpt = $prefixEllipsis
            .substr($pattern, $displayStart, $displayEnd - $displayStart)
            .$suffixEllipsis;

        $caretOffset = ('' === $prefixEllipsis ? 0 : 3) + ($caretIndex - $displayStart);
        if ($caretOffset < 0) {
            $caretOffset = 0;
        }

        $lineLabel = 'Line '.$lineNumber.': ';

        return $lineLabel.$excerpt."\n"
            .str_repeat(' ', \strlen($lineLabel) + $caretOffset).'^';
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
        $allowedClasses = self::getAllowedClasses();
        $exportedAllowedClasses = var_export($allowedClasses, true);
        $version = self::CACHE_VERSION;

        return <<<PHP
            <?php

            declare(strict_types=1);

            if (\RegexParser\Regex::CACHE_VERSION !== $version) {
                return null;
            }

            return unserialize($exportedAst, ['allowed_classes' => $exportedAllowedClasses]);

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
     * @param \Throwable $exception The parse exception
     *
     * @return ValidationResult Validation failure result
     */
    private function buildValidationFailure(\Throwable $exception): ValidationResult
    {
        $errorMessage = $exception->getMessage();
        $visualSnippet = '';
        if (method_exists($exception, 'getVisualSnippet')) {
            $snippet = $exception->getVisualSnippet();
            $visualSnippet = \is_string($snippet) ? $snippet : '';
        }
        $position = null;
        $errorCode = null;
        $hint = null;

        if ($exception instanceof Exception\RegexException) {
            $position = $exception->getPosition();
            $errorCode = $exception->getErrorCode();
        }

        if ($exception instanceof Exception\SemanticErrorException) {
            $hint = $exception->getHint();
        }

        if ('' !== $visualSnippet) {
            $errorMessage .= "\n".$visualSnippet;
        }

        if ($exception instanceof Exception\SemanticErrorException) {
            return new ValidationResult(
                false,
                $errorMessage,
                0,
                ValidationErrorCategory::SEMANTIC,
                $position,
                '' !== $visualSnippet ? $visualSnippet : null,
                $hint,
                $errorCode,
            );
        }

        return new ValidationResult(
            false,
            $errorMessage,
            0,
            ValidationErrorCategory::SYNTAX,
            $position,
            '' !== $visualSnippet ? $visualSnippet : null,
            null,
            $errorCode,
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
