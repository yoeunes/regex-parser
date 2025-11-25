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

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\RegexNode;
use RegexParser\Stream\TokenStream;

/**
 * RegexCompiler - The high-level facade for parsing regex strings.
 *
 * This class combines the Lexer and Parser to provide a convenient API for
 * end-users who want to parse a raw regex string into an AST.
 *
 * Architecture:
 * - String validation (length, delimiters, flags) happens HERE
 * - Lexer tokenizes the pattern into a TokenStream
 * - Parser operates purely on the TokenStream to produce the AST
 *
 * This separation ensures:
 * - Parser has no knowledge of raw strings or lexer internals
 * - Easy testing of Parser with mock TokenStreams
 * - Better modularity and maintainability
 *
 * @example Basic usage:
 * ```php
 * $compiler = new RegexCompiler();
 * $ast = $compiler->parse('/[a-z]+/i');
 * ```
 *
 * @example With custom options:
 * ```php
 * $compiler = new RegexCompiler([
 *     'max_pattern_length' => 50000,
 *     'max_recursion_depth' => 100,
 *     'cache' => $psr16Cache,
 * ]);
 * $ast = $compiler->parse('/complex-pattern/');
 * ```
 *
 * @example Parsing from existing TokenStream (advanced):
 * ```php
 * $lexer = new Lexer($pattern);
 * $stream = new TokenStream($lexer->tokenize());
 * $ast = $parser->parseTokenStream($stream, $flags, $delimiter, strlen($pattern));
 * ```
 */
final class RegexCompiler
{
    /**
     * Default hard limit on the regex string length.
     */
    public const int DEFAULT_MAX_PATTERN_LENGTH = 100_000;

    private readonly int $maxPatternLength;

    /**
     * Runtime cache for parsed ASTs (Layer 1).
     *
     * @var array<string, RegexNode>
     */
    private array $runtimeCache = [];

    private ?CacheInterface $cache = null;

    private ?Lexer $lexer = null;

    private Parser $parser;

    /**
     * @param array{
     *     max_pattern_length?: int,
     *     max_recursion_depth?: int,
     *     max_nodes?: int,
     *     cache?: CacheInterface|null,
     * } $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->maxPatternLength = (int) ($options['max_pattern_length'] ?? self::DEFAULT_MAX_PATTERN_LENGTH);
        $this->cache = $options['cache'] ?? null;

        // Create Parser with its own options (recursion/node limits)
        $this->parser = new Parser($options);
    }

    /**
     * Parses a full regex string (including delimiters and flags) into an AST.
     *
     * Implements a two-layer caching strategy:
     * 1. Runtime Cache (Layer 1): Fast in-memory cache for repeated calls
     * 2. PSR-16 Persistent Cache (Layer 2): Optional external cache
     *
     * @throws ParserException if the regex syntax is invalid
     */
    public function parse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw new ParserException(\sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength));
        }

        // Generate cache key
        $cacheKey = 'regex_parser_'.md5($regex);

        // Layer 1: Check runtime cache
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        // Layer 2: Check persistent cache (if available)
        if (null !== $this->cache) {
            try {
                $cached = $this->cache->get($cacheKey);
                if ($cached instanceof RegexNode) {
                    $this->runtimeCache[$cacheKey] = $cached;

                    return $cached;
                }
            } catch (InvalidArgumentException) {
                // Cache key is invalid - proceed with parsing
            }
        }

        // Cache miss - proceed with actual parsing
        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        // Tokenize the pattern
        $lexer = $this->getLexer($pattern);
        $stream = new TokenStream($lexer->tokenize());

        // Parse the token stream
        $ast = $this->parser->parseTokenStream($stream, $flags, $delimiter, \strlen($pattern));

        // Save to runtime cache (Layer 1)
        $this->runtimeCache[$cacheKey] = $ast;

        // Save to persistent cache (Layer 2) if available
        if (null !== $this->cache) {
            try {
                $this->cache->set($cacheKey, $ast);
            } catch (InvalidArgumentException) {
                // Cache write failed - continue without caching
            }
        }

        return $ast;
    }

    /**
     * Gets a cached AST if available, null otherwise.
     * Useful for checking cache state without triggering a parse.
     */
    public function getCached(string $regex): ?RegexNode
    {
        $cacheKey = 'regex_parser_'.md5($regex);

        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        if (null !== $this->cache) {
            try {
                $cached = $this->cache->get($cacheKey);
                if ($cached instanceof RegexNode) {
                    return $cached;
                }
            } catch (InvalidArgumentException) {
                // Ignore cache errors
            }
        }

        return null;
    }

    /**
     * Clears the runtime cache.
     */
    public function clearRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    /**
     * Returns the underlying Parser instance.
     * Useful for advanced scenarios where direct TokenStream parsing is needed.
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    private function getLexer(string $pattern): Lexer
    {
        if (null === $this->lexer) {
            $this->lexer = new Lexer($pattern);
        } else {
            $this->lexer->reset($pattern);
        }

        return $this->lexer;
    }

    /**
     * Extracts pattern, flags, and delimiter from a regex string.
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     */
    private function extractPatternAndFlags(string $regex): array
    {
        $len = \strlen($regex);
        if ($len < 2) {
            throw new ParserException('Regex is too short. It must include delimiters.');
        }

        $delimiter = $regex[0];
        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // Find the last occurrence of the closing delimiter that is NOT escaped
        for ($i = $len - 1; $i > 0; $i--) {
            if ($regex[$i] === $closingDelimiter) {
                $escapes = 0;
                for ($j = $i - 1; $j > 0 && '\\' === $regex[$j]; $j--) {
                    $escapes++;
                }

                if (0 === $escapes % 2) {
                    $pattern = substr($regex, 1, $i - 1);
                    $flags = substr($regex, $i + 1);

                    if (!preg_match('/^[imsxADSUXJu]*$/', $flags)) {
                        $invalid = preg_replace('/[imsxADSUXJu]/', '', $flags);

                        throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $invalid ?? $flags));
                    }

                    return [$pattern, $flags, $delimiter];
                }
            }
        }

        throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
    }
}
