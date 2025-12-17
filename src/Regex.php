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
use RegexParser\Node\RegexNode;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSAnalyzer;
use RegexParser\ReDoS\ReDoSSeverity;

final readonly class Regex
{
    public const DEFAULT_MAX_PATTERN_LENGTH = 100_000;
    public const DEFAULT_MAX_LOOKBEHIND_LENGTH = 255;

    /**
     * @param array<string> $redosIgnoredPatterns
     */
    private function __construct(
        private int $maxPatternLength,
        private int $maxLookbehindLength,
        private CacheInterface $cache,
        private array $redosIgnoredPatterns,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public static function create(array $options = []): self
    {
        $parsedOptions = RegexOptions::fromArray($options);

        $redosIgnoredPatterns = $parsedOptions->redosIgnoredPatterns;

        return new self(
            $parsedOptions->maxPatternLength,
            $parsedOptions->maxLookbehindLength,
            $parsedOptions->cache,
            $redosIgnoredPatterns,
        );
    }

    public function parse(string $regex): RegexNode
    {
        $ast = $this->doParse($regex, false);

        return $ast instanceof RegexNode ? $ast : $ast->ast;
    }

    public function parseTolerant(string $regex): TolerantParseResult
    {
        $result = $this->doParse($regex, true);

        return $result instanceof TolerantParseResult ? $result : new TolerantParseResult($result);
    }

    public function parsePattern(string $pattern, string $delimiter = '/', string $flags = ''): RegexNode
    {
        if (1 !== \strlen($delimiter) || ctype_alnum($delimiter)) {
            throw new ParserException('Delimiter must be a single non-alphanumeric character.');
        }

        $regex = $delimiter.$pattern.$delimiter.$flags;

        return $this->parse($regex);
    }

    public function validate(string $regex): ValidationResult
    {
        try {
            $pattern = null;

            try {
                [$pattern] = $this->extractPatternAndFlags($regex);
            } catch (ParserException) {
            }

            $ast = $this->parse($regex);
            $ast->accept(new NodeVisitor\ValidatorNodeVisitor($this->maxLookbehindLength, $pattern));
            $score = $ast->accept(new NodeVisitor\ComplexityScoreNodeVisitor());

            $this->validatePcreRuntime($regex, $pattern);

            return new ValidationResult(true, null, $score);
        } catch (LexerException|ParserException $e) {
            $message = $e->getMessage();
            $snippet = $e->getVisualSnippet();
            $hint = null;
            $code = null;
            $category = ValidationErrorCategory::SYNTAX;

            if ($e instanceof Exception\SemanticErrorException) {
                $category = ValidationErrorCategory::SEMANTIC;
                $hint = $e->getHint();
                $code = $e->getErrorCode();
            }

            if ($e instanceof Exception\PcreRuntimeException) {
                $category = ValidationErrorCategory::PCRE_RUNTIME;
                $code = $e->getErrorCode();
            }

            if ('' !== $snippet) {
                $message .= "\n".$snippet;
            }

            return new ValidationResult(
                false,
                $message,
                0,
                $category,
                $e->getPosition(),
                '' !== $snippet ? $snippet : null,
                $hint,
                $code,
            );
        }
    }

    public function isValid(string $regex): bool
    {
        return $this->validate($regex)->isValid();
    }

    public function assertValid(string $regex): void
    {
        $result = $this->validate($regex);
        if (!$result->isValid()) {
            $message = $result->getErrorMessage() ?? 'Invalid regex pattern.';
            $offset = $result->getErrorOffset();
            $pattern = null;

            try {
                [$pattern] = $this->extractPatternAndFlags($regex);
            } catch (ParserException) {
                $pattern = null;
            }

            if (ValidationErrorCategory::SEMANTIC === $result->getErrorCategory()) {
                throw new Exception\SemanticErrorException(
                    $message,
                    $offset,
                    $pattern,
                    null,
                    $result->getErrorCode() ?? 'regex.semantic',
                    $result->getHint(),
                );
            }

            if (ValidationErrorCategory::PCRE_RUNTIME === $result->getErrorCategory()) {
                throw new Exception\PcreRuntimeException(
                    $message,
                    $offset,
                    $pattern,
                    null,
                    $result->getErrorCode(),
                );
            }

            throw new ParserException($message, $offset, $pattern);
        }
    }

    public function analyzeReDoS(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        return (new ReDoSAnalyzer($this, array_values($this->redosIgnoredPatterns)))->analyze($regex, $threshold);
    }

    public function isSafe(string $regex, ?ReDoSSeverity $threshold = null): bool
    {
        $analysis = $this->analyzeReDoS($regex, $threshold);

        return null === $threshold ? $analysis->isSafe() : !$analysis->exceedsThreshold($threshold);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    public function getLengthRange(string $regex)
    {
        return $this->parse($regex)->accept(new NodeVisitor\LengthRangeNodeVisitor());
    }

    public function extractLiterals(string $regex): LiteralExtractionResult
    {
        $literalSet = $this->parse($regex)->accept(new NodeVisitor\LiteralExtractorNodeVisitor());
        $literals = array_values(array_unique(array_merge($literalSet->prefixes, $literalSet->suffixes)));
        $patterns = [];

        foreach ($literalSet->prefixes as $prefix) {
            if ('' !== $prefix) {
                $patterns[] = '^'.preg_quote($prefix, '/');
            }
        }

        foreach ($literalSet->suffixes as $suffix) {
            if ('' !== $suffix) {
                $patterns[] = preg_quote($suffix, '/').'$';
            }
        }

        $patterns = array_values(array_unique($patterns));

        $confidence = $literalSet->complete && !$literalSet->isVoid()
            ? 'high'
            : (!$literalSet->isVoid() ? 'medium' : 'low');

        return new LiteralExtractionResult($literals, $patterns, $confidence, $literalSet);
    }

    public function optimize(string $regex): OptimizationResult
    {
        $optimized = $this->transformAndCompile($regex, new NodeVisitor\OptimizerNodeVisitor());
        $changes = $optimized === $regex ? [] : ['Optimized pattern.'];

        return new OptimizationResult($regex, $optimized, $changes);
    }

    public function modernize(string $regex): string
    {
        return $this->transformAndCompile($regex, new NodeVisitor\ModernizerNodeVisitor());
    }

    public function generate(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\SampleGeneratorNodeVisitor());
    }

    public function generateTestCases(string $regex): TestCaseGenerationResult
    {
        $cases = $this->parse($regex)->accept(new NodeVisitor\TestCaseGeneratorNodeVisitor());

        return new TestCaseGenerationResult(
            array_values($cases['matching']),
            array_values($cases['non_matching']),
            ['Generated samples are heuristic; validate with real inputs.'],
        );
    }

    public function visualize(string $regex): VisualizationResult
    {
        $mermaid = $this->parse($regex)->accept(new NodeVisitor\MermaidNodeVisitor());

        return new VisualizationResult($mermaid);
    }

    public function dump(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\DumperNodeVisitor());
    }

    public function highlight(string $regex, string $format = 'auto'): string
    {
        if ('auto' === $format) {
            $format = $this->isCli() ? 'cli' : 'html';
        }

        $visitor = match ($format) {
            'cli' => new NodeVisitor\ConsoleHighlighterVisitor(),
            'html' => new NodeVisitor\HtmlHighlighterVisitor(),
            default => throw new \InvalidArgumentException("Invalid format: $format"),
        };

        return $this->parse($regex)->accept($visitor);
    }

    public function highlightCli(string $regex): string
    {
        return $this->highlight($regex, 'cli');
    }

    public function highlightHtml(string $regex): string
    {
        return $this->highlight($regex, 'html');
    }

    public function explain(string $regex, string $format = 'text'): string
    {
        $visitor = match ($format) {
            'text' => new NodeVisitor\ExplainNodeVisitor(),
            'html' => new NodeVisitor\HtmlExplainNodeVisitor(),
            default => throw new \InvalidArgumentException("Invalid format: $format"),
        };

        return $this->parse($regex)->accept($visitor);
    }

    public function htmlExplain(string $regex): string
    {
        return $this->explain($regex, 'html');
    }

    /**
     * @param iterable<string> $regexes
     */
    public function warm(iterable $regexes): void
    {
        foreach ($regexes as $regex) {
            $this->parse($regex); // hits cache
            $this->analyzeReDoS($regex);
        }
    }

    /**
     * @return array<string>
     */
    public function getRedosIgnoredPatterns(): array
    {
        return array_values($this->redosIgnoredPatterns);
    }

    public function getParser(): Parser
    {
        return new Parser();
    }

    public function getLexer(): Lexer
    {
        return new Lexer();
    }

    public function createTokenStream(string $pattern): TokenStream
    {
        return $this->getLexer()->tokenize($pattern);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function extractPatternAndFlags(string $regex): array
    {
        // Trim leading whitespace to match PHP's PCRE behavior
        $regex = ltrim($regex);

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
                    if (!preg_match('/^[imsxADSUXJunr]*+$/', $flags)) {
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

    private function validatePcreRuntime(string $regex, ?string $pattern): void
    {
        $errorMessage = null;
        $handler = static function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;

            return true;
        };

        set_error_handler($handler);
        $result = @preg_match($regex, '');
        restore_error_handler();

        if (false === $result) {
            $message = $errorMessage ?? preg_last_error_msg();
            $message = '' !== $message ? $message : 'PCRE runtime error.';

            throw new Exception\PcreRuntimeException($message, null, $pattern, null, 'regex.pcre.runtime');
        }
    }

    /**
     * @param NodeVisitor\NodeVisitorInterface<Node\NodeInterface> $transformer
     */
    private function transformAndCompile(string $regex, NodeVisitor\NodeVisitorInterface $transformer): string
    {
        $ast = $this->parse($regex);
        /** @var Node\NodeInterface $transformed */
        $transformed = $ast->accept($transformer);

        return $transformed->accept(new NodeVisitor\CompilerNodeVisitor());
    }

    private function isCli(): bool
    {
        return \PHP_SAPI === 'cli';
    }

    /**
     * @return array{0: \RegexParser\Node\RegexNode|null, 1: string|null}
     */
    private function loadFromCache(string $regex): array
    {
        if ($this->cache instanceof NullCache) {
            return [null, null];
        }

        $cacheKey = $this->cache->generateKey($regex);
        $cached = $this->cache->load($cacheKey);

        return [$cached instanceof RegexNode ? $cached : null, $cacheKey];
    }

    private function storeInCache(?string $cacheKey, RegexNode $ast): void
    {
        if (null === $cacheKey) {
            return;
        }

        try {
            $this->cache->write($cacheKey, self::compileCachePayload($ast));
        } catch (\Throwable) {
        }
    }

    private static function compileCachePayload(RegexNode $ast): string
    {
        $serialized = serialize($ast);
        $exported = var_export($serialized, true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            return unserialize($exported, ['allowed_classes' => true]);

            PHP;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: int}
     */
    private function safeExtractPattern(string $regex): array
    {
        try {
            [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);
            $pattern = (string) $pattern;
            $flags = (string) $flags;
            $delimiter = (string) $delimiter;
            $length = \strlen((string) $pattern);

            return [$pattern, $flags, $delimiter, $length];
        } catch (ParserException) {
            return [$regex, '', '/', \strlen($regex)];
        }
    }

    private function buildFallbackAst(string $pattern, string $flags, string $delimiter, int $patternLength, ?int $errorPosition): Node\RegexNode
    {
        $value = null === $errorPosition ? $pattern : substr($pattern, 0, max(0, $errorPosition));
        $literal = new Node\LiteralNode($value, 0, \strlen($value));
        $sequence = new Node\SequenceNode([$literal], 0, $literal->getEndPosition());

        return new Node\RegexNode($sequence, $flags, $delimiter, 0, $patternLength);
    }

    private function doParse(string $regex, bool $tolerant): RegexNode|TolerantParseResult
    {
        try {
            $ast = $this->performParse($regex);

            return $tolerant ? new TolerantParseResult($ast) : $ast;
        } catch (LexerException|ParserException $e) {
            if (!$tolerant) {
                throw $e;
            }

            [$pattern, $flags, $delimiter, $length] = $this->safeExtractPattern($regex);
            $ast = $this->buildFallbackAst($pattern, $flags, $delimiter, $length, $e->getPosition());

            return new TolerantParseResult($ast, [$e]);
        }
    }

    private function performParse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw ResourceLimitException::withContext(
                \sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength),
                $this->maxPatternLength,
                $regex,
            );
        }

        [$cached, $cacheKey] = $this->loadFromCache($regex);
        if (null !== $cached) {
            return $cached;
        }

        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $stream = $this->getLexer()->tokenize($pattern);
        $parser = $this->getParser();

        $ast = $parser->parse($stream, $flags, $delimiter, \strlen($pattern));

        $this->storeInCache((string) $cacheKey, $ast);

        return $ast;
    }
}
