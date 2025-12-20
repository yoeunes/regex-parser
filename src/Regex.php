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

    /**
     * @return ($tolerant is true ? TolerantParseResult : RegexNode)
     */
    public function parse(string $regex, bool $tolerant = false): RegexNode|TolerantParseResult
    {
        return $this->doParse($regex, $tolerant);
    }

    public function validate(string $regex): ValidationResult
    {
        try {
            $pattern = null;

            try {
                [$pattern] = PatternParser::extractPatternAndFlags($regex);
            } catch (ParserException) {
            }

            $ast = $this->parse($regex);
            $ast->accept(new NodeVisitor\ValidatorNodeVisitor($this->maxLookbehindLength, $pattern));
            $score = $ast->accept(new NodeVisitor\ComplexityScoreNodeVisitor());

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

    public function redos(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        return (new ReDoSAnalyzer($this, array_values($this->redosIgnoredPatterns)))->analyze($regex, $threshold);
    }

    public function literals(string $regex): LiteralExtractionResult
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

    public function generate(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\SampleGeneratorNodeVisitor());
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
            [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);
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

        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);

        $stream = (new Lexer())->tokenize($pattern);
        $parser = new Parser();

        $ast = $parser->parse($stream, $flags, $delimiter, \strlen($pattern));

        $this->storeInCache((string) $cacheKey, $ast);

        return $ast;
    }
}
