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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Parser;

/**
 * Tests that verify parsed patterns behave identically to PHP's PCRE engine.
 * This ensures the library correctly implements PCRE semantics, not just AST structure.
 */
class BehavioralComplianceTest extends TestCase
{
    /**
     * Test that round-trip compiled patterns match the same strings as the original.
     *
     * @return \Iterator<string, array{pattern: string, testCases: array<string, bool>}>
     */
    public static function providePatternsWithBehavior(): \Iterator
    {
        // Basic patterns
        yield 'simple literal' => [
            'pattern' => '/abc/',
            'testCases' => [
                'abc' => true,
                'ABC' => false,
                'abcd' => true,  // partial match
                'xabc' => true,  // partial match
                'ab' => false,
            ],
        ];

        yield 'case insensitive flag' => [
            'pattern' => '/test/i',
            'testCases' => [
                'test' => true,
                'TEST' => true,
                'TeSt' => true,
                'testing' => true,
                'tes' => false,
            ],
        ];

        yield 'character class' => [
            'pattern' => '/[a-z]+/',
            'testCases' => [
                'hello' => true,
                'HELLO' => false,
                'hello123' => true,
                'num123' => true, // "num" matches [a-z]+
            ],
        ];

        yield 'negated character class' => [
            'pattern' => '/[^0-9]+/',
            'testCases' => [
                'abc' => true,
                'num123' => true, // "num" matches [^0-9]+
                'abc123' => true,
            ],
        ];

        yield 'quantifiers' => [
            'pattern' => '/a+b*c?/',
            'testCases' => [
                'a' => true,
                'ab' => true,
                'abc' => true,
                'aabbbbc' => true,
                'b' => false,
            ],
        ];

        yield 'anchors' => [
            'pattern' => '/^test$/',
            'testCases' => [
                'test' => true,
                ' test' => false,
                'test ' => false,
                'testing' => false,
            ],
        ];

        yield 'alternation' => [
            'pattern' => '/foo|bar/',
            'testCases' => [
                'foo' => true,
                'bar' => true,
                'foobar' => true,
                'baz' => false,
            ],
        ];

        yield 'capturing groups' => [
            'pattern' => '/(hello)/',
            'testCases' => [
                'hello' => true,
                'world' => false,
                'hello world' => true,
            ],
        ];

        yield 'non-capturing groups' => [
            'pattern' => '/(?:foo|bar)+/',
            'testCases' => [
                'foo' => true,
                'bar' => true,
                'foobar' => true,
                'foobarbaz' => true,
            ],
        ];

        yield 'named groups' => [
            'pattern' => '/(?<word>\w+)/',
            'testCases' => [
                'hello' => true,
                'num123' => true,
                '!' => false,
            ],
        ];

        yield 'backreferences' => [
            'pattern' => '/(a)\1/',
            'testCases' => [
                'aa' => true,
                'ab' => false,
                'aaa' => true,  // matches first two 'aa'
            ],
        ];

        yield 'lookahead' => [
            'pattern' => '/foo(?=bar)/',
            'testCases' => [
                'foobar' => true,
                'foobaz' => false,
                'foo' => false,
            ],
        ];

        yield 'negative lookahead' => [
            'pattern' => '/foo(?!bar)/',
            'testCases' => [
                'foo' => true,  // 'foo' not followed by 'bar'
                'foobaz' => true,
                'foobar' => false,
            ],
        ];

        yield 'lookbehind' => [
            'pattern' => '/(?<=foo)bar/',
            'testCases' => [
                'foobar' => true,
                'bar' => false,
                'xbar' => false,
            ],
        ];

        yield 'atomic groups' => [
            'pattern' => '/(?>a+)b/',
            'testCases' => [
                'ab' => true,
                'aab' => true,
                'aaab' => true,
            ],
        ];

        yield 'unicode properties' => [
            'pattern' => '/\p{L}+/u',
            'testCases' => [
                'hello' => true,
                'Héllo' => true,
                'num123' => true, // "num" matches \p{L}+
                'hello123' => true,
            ],
        ];

        yield 'word boundaries' => [
            'pattern' => '/\btest\b/',
            'testCases' => [
                'test' => true,
                'testing' => false,
                'a test b' => true,
                'contest' => false,
            ],
        ];
    }

    /**
     * @param array<string, bool> $testCases
     */
    #[DataProvider('providePatternsWithBehavior')]
    public function test_pattern_behavior_matches_pcre(string $pattern, array $testCases): void
    {
        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        // Parse and recompile the pattern
        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        // Test that both patterns behave identically
        foreach ($testCases as $input => $shouldMatch) {
            $input = (string) $input; // Ensure input is string
            $originalMatch = (bool) @preg_match($pattern, $input);
            $compiledMatch = (bool) @preg_match($compiled, $input);

            $this->assertSame(
                $originalMatch,
                $compiledMatch,
                \sprintf(
                    "Pattern behavior mismatch for input '%s':\n".
                    "Original pattern: %s (matches: %s)\n".
                    "Compiled pattern: %s (matches: %s)\n".
                    'Expected: %s',
                    $input,
                    $pattern,
                    $originalMatch ? 'YES' : 'NO',
                    $compiled,
                    $compiledMatch ? 'YES' : 'NO',
                    $shouldMatch ? 'MATCH' : 'NO MATCH',
                ),
            );

            $this->assertSame(
                $shouldMatch,
                $originalMatch,
                \sprintf(
                    "Test case expectation incorrect for '%s' with pattern %s",
                    $input,
                    $pattern,
                ),
            );
        }
    }

    /**
     * Test that captured groups match identically.
     */
    public function test_captured_groups_match_pcre(): void
    {
        $pattern = '/(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/';
        $input = '2025-11-24';

        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        preg_match($pattern, $input, $originalMatches);
        preg_match($compiled, $input, $compiledMatches);

        $this->assertSame($originalMatches, $compiledMatches);
    }

    /**
     * Test that substitutions work identically.
     */
    public function test_substitutions_match_pcre(): void
    {
        $pattern = '/(\w+)\s+(\w+)/';
        $replacement = '$2 $1';
        $input = 'hello world';

        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        $originalResult = preg_replace($pattern, $replacement, $input);
        $compiledResult = preg_replace($compiled, $replacement, $input);

        $this->assertSame($originalResult, $compiledResult);
    }
}
