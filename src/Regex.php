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

    private const DEFAULT_REDOS_IGNORED_PATTERNS = [
        '[a-z0-9]+(?:-[a-z0-9]+)*',
        '^[a-z0-9]+(?:-[a-z0-9]+)*$',
        '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*',
        '^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$',
        '[a-z0-9_]+',
        '^[a-z0-9_]+$',
        '[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}',
        '^\d+$',
        '^\d{4}-\d{2}-\d{2}$',
        '[0-9a-fA-F]{24}',
        '[1-9]\d*',
        '[1-9]\d{3,}',
        '[A-Za-z0-9]{26}',
        '[1-9A-HJ-NP-Za-km-z]{21,22}',
        '[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}',
        '^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$',
    ];

    private function __construct(
        private int $maxPatternLength,
        private CacheInterface $cache,
        private array $redosIgnoredPatterns,
    ) {}

    public static function create(array $options = []): self
    {
        $parsedOptions = RegexOptions::fromArray($options);

        $redosIgnoredPatterns = array_values(array_unique([
            ...self::DEFAULT_REDOS_IGNORED_PATTERNS,
            ...$parsedOptions->redosIgnoredPatterns,
        ]));

        return new self($parsedOptions->maxPatternLength, $parsedOptions->cache, $redosIgnoredPatterns);
    }

    public function parse(string $regex): RegexNode
    {
        $ast = $this->doParse($regex, false);

        return $ast instanceof RegexNode ? $ast : $ast->ast;
    }

    public function validate(string $regex): ValidationResult
    {
        try {
            $ast = $this->parse($regex);
            $ast->accept(new NodeVisitor\ValidatorNodeVisitor());
            $score = $ast->accept(new NodeVisitor\ComplexityScoreNodeVisitor());

            return new ValidationResult(true, null, $score);
        } catch (LexerException|ParserException $e) {
            $message = $e->getMessage();
            $snippet = $e->getVisualSnippet();

            if ('' !== $snippet) {
                $message .= "\n".$snippet;
            }

            return new ValidationResult(false, $message);
        }
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

    public function generate(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\SampleGeneratorNodeVisitor());
    }

    public function optimize(string $regex): string
    {
        return $this->transformAndCompile($regex, new NodeVisitor\OptimizerNodeVisitor());
    }

    public function getLengthRange(string $regex)
    {
        return $this->parse($regex)->accept(new NodeVisitor\LengthRangeNodeVisitor());
    }

    public function generateTestCases(string $regex)
    {
        return $this->parse($regex)->accept(new NodeVisitor\TestCaseGeneratorNodeVisitor());
    }

    public function visualize(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\MermaidNodeVisitor());
    }

    public function dump(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\DumperNodeVisitor());
    }

    public function modernize(string $regex): string
    {
        return $this->transformAndCompile($regex, new NodeVisitor\ModernizerNodeVisitor());
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

    public function extractLiterals(string $regex): LiteralSet
    {
        return $this->parse($regex)->accept(new NodeVisitor\LiteralExtractorNodeVisitor());
    }

    public function analyzeReDoS(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        return (new ReDoSAnalyzer($this, $this->redosIgnoredPatterns))->analyze($regex, $threshold);
    }

    public function isSafe(string $regex, ?ReDoSSeverity $threshold = null): bool
    {
        $analysis = $this->analyzeReDoS($regex, $threshold);

        return null === $threshold ? $analysis->isSafe() : !$analysis->exceedsThreshold($threshold);
    }

    public function isValid(string $regex): bool
    {
        return $this->validate($regex)->isValid();
    }

    public function assertValid(string $regex): void
    {
        $result = $this->validate($regex);
        if (!$result->isValid()) {
            throw new ParserException($result->getErrorMessage() ?? 'Invalid regex pattern.');
        }
    }

    public function warm(iterable $regexes): void
    {
        foreach ($regexes as $regex) {
            $this->parse($regex); // hits cache
            $this->analyzeReDoS($regex);
        }
    }

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

    public function createTokenStream(string $pattern): TokenStream
    {
        return $this->getLexer()->tokenize($pattern);
    }

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

    private function safeExtractPattern(string $regex): array
    {
        try {
            [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);
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

        $ast = $parser->parse($stream, $flags, $delimiter, \strlen((string) $pattern));

        $this->storeInCache($cacheKey, $ast);

        return $ast;
    }
}
