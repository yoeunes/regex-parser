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

namespace RegexParser\Tests\TestUtils;

use RegexParser\Lexer;
use RegexParser\Node\RegexNode;
use RegexParser\Parser;
use RegexParser\RegexCompiler;
use RegexParser\Stream\TokenStream;

/**
 * Test utility for creating parser instances and parsing patterns.
 *
 * Provides convenience methods for tests that need to parse regex patterns.
 * Uses RegexCompiler for string parsing (recommended) or provides access to
 * the low-level Parser for TokenStream parsing.
 */
final class ParserHelper
{
    private static ?RegexCompiler $compiler = null;

    private static ?Parser $parser = null;

    /**
     * Parses a regex string into an AST using RegexCompiler.
     * This is the primary method for parsing regex strings.
     */
    public static function parse(string $regex): RegexNode
    {
        return self::getCompiler()->parse($regex);
    }

    /**
     * Returns a shared RegexCompiler instance for test efficiency.
     */
    public static function getCompiler(): RegexCompiler
    {
        if (null === self::$compiler) {
            self::$compiler = new RegexCompiler();
        }

        return self::$compiler;
    }

    /**
     * Returns a shared Parser instance.
     */
    public static function getParser(): Parser
    {
        if (null === self::$parser) {
            self::$parser = new Parser();
        }

        return self::$parser;
    }

    /**
     * Creates a fresh Parser instance with custom options.
     *
     * @param array{max_recursion_depth?: int, max_nodes?: int} $options
     */
    public static function createParser(array $options = []): Parser
    {
        return new Parser($options);
    }

    /**
     * Creates a fresh RegexCompiler instance with custom options.
     *
     * @param array{max_pattern_length?: int, max_recursion_depth?: int, max_nodes?: int} $options
     */
    public static function createCompiler(array $options = []): RegexCompiler
    {
        return new RegexCompiler($options);
    }

    /**
     * Parses a pattern directly using TokenStream (advanced usage).
     *
     * @param string $pattern   The raw pattern (without delimiters)
     * @param string $flags     Regex flags (e.g., 'i', 'ms')
     * @param string $delimiter The delimiter used
     */
    public static function parseTokenStream(
        string $pattern,
        string $flags = '',
        string $delimiter = '/'
    ): RegexNode {
        $lexer = new Lexer($pattern);
        $stream = new TokenStream($lexer->tokenize());

        return self::getParser()->parseTokenStream($stream, $flags, $delimiter, \strlen($pattern));
    }

    /**
     * Resets cached instances. Useful between test cases.
     */
    public static function reset(): void
    {
        self::$compiler = null;
        self::$parser = null;
    }
}
