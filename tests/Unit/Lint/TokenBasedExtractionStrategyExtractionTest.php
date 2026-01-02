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

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\TokenBasedExtractionStrategy;

final class TokenBasedExtractionStrategyExtractionTest extends TestCase
{
    public function test_match_function_call_ignores_namespaced_function(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'Foo', 1],
            [\T_NS_SEPARATOR, '\\', 1],
            [\T_STRING, 'preg_match', 1],
            '(',
        ];
        $index = 2;

        $result = $this->invoke($strategy, 'matchFunctionCall', $tokens, $index, \count($tokens));

        $this->assertNull($result);
    }

    public function test_match_function_call_rejects_backslashed_name(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'Foo\\preg_match', 1],
            '(',
        ];

        $result = $this->invoke($strategy, 'matchFunctionCall', $tokens, 0, \count($tokens));

        $this->assertNull($result);
    }

    public function test_match_custom_static_method_edge_cases(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['Foo::bar']);

        $missingMethod = [
            [\T_STRING, 'Foo', 1],
            [\T_DOUBLE_COLON, '::', 1],
        ];
        $this->assertNull($this->invoke($strategy, 'matchCustomStaticMethod', $missingMethod, 0, 1, \count($missingMethod)));

        $nonStringMethod = [
            [\T_STRING, 'Foo', 1],
            [\T_DOUBLE_COLON, '::', 1],
            [\T_VARIABLE, '$bar', 1],
            '(',
        ];
        $this->assertNull($this->invoke($strategy, 'matchCustomStaticMethod', $nonStringMethod, 0, 1, \count($nonStringMethod)));

        $missingParen = [
            [\T_STRING, 'Foo', 1],
            [\T_DOUBLE_COLON, '::', 1],
            [\T_STRING, 'bar', 1],
            ';',
        ];
        $this->assertNull($this->invoke($strategy, 'matchCustomStaticMethod', $missingParen, 0, 1, \count($missingParen)));

        $badClassToken = [
            'Foo',
            [\T_DOUBLE_COLON, '::', 1],
            [\T_STRING, 'bar', 1],
            '(',
        ];
        $this->assertNull($this->invoke($strategy, 'matchCustomStaticMethod', $badClassToken, 0, 1, \count($badClassToken)));

        $valid = [
            [\T_STRING, 'Foo', 1],
            [\T_DOUBLE_COLON, '::', 1],
            [\T_STRING, 'bar', 1],
            '(',
        ];
        $result = $this->invoke($strategy, 'matchCustomStaticMethod', $valid, 0, 1, \count($valid));
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertIsString($result[0]);
        $this->assertSame('Foo::bar', $result[0]);
    }

    public function test_extract_from_call_collects_target_argument(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = token_get_all('<?php preg_replace("/foo/", "bar", $subject);');
        $nameIndex = $this->findTokenIndex($tokens, 'preg_replace');
        $openParenIndex = $this->findNextToken($tokens, '(', $nameIndex);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCall',
            $tokens,
            $openParenIndex + 1,
            \count($tokens),
            1,
            'preg_replace',
            'test.php',
            false,
            $tokenOffsets,
            $content,
        );

        $this->assertIsArray($occurrences);
        $this->assertCount(1, $occurrences);
        $this->assertInstanceOf(RegexPatternOccurrence::class, $occurrences[0]);
        $this->assertSame('bar', $occurrences[0]->pattern);
        $this->assertSame('preg_replace()', $occurrences[0]->source);
    }

    public function test_extract_from_call_tracks_brace_depth(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            '{',
            '}',
            ')',
        ];
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCall',
            $tokens,
            0,
            \count($tokens),
            0,
            'preg_match',
            'test.php',
            false,
            $tokenOffsets,
            $content,
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_call_handles_incomplete_arguments(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "'/a/'", 1],
        ];
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCall',
            $tokens,
            0,
            \count($tokens),
            0,
            'preg_match',
            'test.php',
            false,
            $tokenOffsets,
            $content,
        );

        $this->assertIsArray($occurrences);
        $this->assertCount(1, $occurrences);
        $this->assertInstanceOf(RegexPatternOccurrence::class, $occurrences[0]);
        $this->assertSame('/a/', $occurrences[0]->pattern);
    }

    public function test_extract_from_call_returns_empty_when_never_collecting(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "'/a/'", 1],
        ];
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCall',
            $tokens,
            0,
            \count($tokens),
            1,
            'preg_match',
            'test.php',
            false,
            $tokenOffsets,
            $content,
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_call_returns_empty_when_target_missing(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = token_get_all('<?php preg_match("/foo/", $subject);');
        $nameIndex = $this->findTokenIndex($tokens, 'preg_match');
        $openParenIndex = $this->findNextToken($tokens, '(', $nameIndex);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCall',
            $tokens,
            $openParenIndex + 1,
            \count($tokens),
            3,
            'preg_match',
            'test.php',
            false,
            $tokenOffsets,
            $content,
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_argument_tokens_returns_empty_for_invalid_tokens(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_VARIABLE, '$pattern', 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromArgumentTokens',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_match',
            false,
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_argument_tokens_skips_empty_pattern(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "''", 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromArgumentTokens',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_match',
            false,
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_callback_array_collects_patterns(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_ARRAY, 'array', 1],
            '(',
            [\T_CONSTANT_ENCAPSED_STRING, "'/foo/'", 1],
            [\T_DOUBLE_ARROW, '=>', 1],
            [\T_CONSTANT_ENCAPSED_STRING, "'cb'", 1],
            ',',
            [\T_CONSTANT_ENCAPSED_STRING, "'/bar/'", 1],
            [\T_DOUBLE_ARROW, '=>', 1],
            [\T_CONSTANT_ENCAPSED_STRING, "'cb2'", 1],
            ')',
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCallbackArray',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_replace_callback_array',
        );

        $this->assertIsArray($occurrences);
        $this->assertCount(2, $occurrences);
        $this->assertInstanceOf(RegexPatternOccurrence::class, $occurrences[0]);
        $this->assertInstanceOf(RegexPatternOccurrence::class, $occurrences[1]);
        $this->assertSame('/foo/', $occurrences[0]->pattern);
        $this->assertSame('/bar/', $occurrences[1]->pattern);
    }

    public function test_extract_from_callback_array_returns_empty_when_no_array_start(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'foo', 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCallbackArray',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_replace_callback_array',
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_callback_array_handles_nested_arrays(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_ARRAY, 'array', 1],
            '(',
            [\T_ARRAY, 'array', 1],
            '(',
            [\T_CONSTANT_ENCAPSED_STRING, "'/foo/'", 1],
            [\T_DOUBLE_ARROW, '=>', 1],
            [\T_CONSTANT_ENCAPSED_STRING, "'cb'", 1],
            ')',
            ')',
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCallbackArray',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_replace_callback_array',
        );

        $this->assertSame([], $occurrences);
    }

    public function test_extract_from_callback_array_handles_parenthesized_keys(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            '[',
            '(',
            [\T_CONSTANT_ENCAPSED_STRING, "'/foo/'", 1],
            ')',
            [\T_DOUBLE_ARROW, '=>', 1],
            [\T_CONSTANT_ENCAPSED_STRING, "'cb'", 1],
            ']',
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $occurrences = $this->invoke(
            $strategy,
            'extractFromCallbackArray',
            $tokens,
            $tokenIndexes,
            $tokenOffsets,
            $content,
            'test.php',
            'preg_replace_callback_array',
        );

        $this->assertIsArray($occurrences);
        $this->assertCount(1, $occurrences);
        $this->assertInstanceOf(RegexPatternOccurrence::class, $occurrences[0]);
        $this->assertSame('/foo/', $occurrences[0]->pattern);
    }

    public function test_parse_regex_expression_preserves_flags(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "'/foo/i'", 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $result = $this->invoke($strategy, 'parseRegexExpression', $tokens, $tokenIndexes, $tokenOffsets, $content);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pattern', $result);
        $this->assertSame('/foo/i', $result['pattern']);
    }

    public function test_parse_constant_string_expression_handles_concatenation(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "'foo'", 1],
            '.',
            [\T_CONSTANT_ENCAPSED_STRING, "'bar'", 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $result = $this->invoke($strategy, 'parseConstantStringExpression', $tokens, $tokenIndexes, $tokenOffsets, $content);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pattern', $result);
        $this->assertSame('foobar', $result['pattern']);
    }

    public function test_parse_constant_string_expression_skips_parentheses(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            '(',
            [\T_CONSTANT_ENCAPSED_STRING, "'foo'", 1],
            ')',
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $result = $this->invoke($strategy, 'parseConstantStringExpression', $tokens, $tokenIndexes, $tokenOffsets, $content);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pattern', $result);
        $this->assertSame('foo', $result['pattern']);
    }

    public function test_parse_constant_string_expression_returns_null_for_invalid_sequence(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_CONSTANT_ENCAPSED_STRING, "'foo'", 1],
            [\T_VARIABLE, '$bar', 1],
        ];

        $tokenIndexes = array_keys($tokens);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $content = $this->tokensToContent($tokens);

        $result = $this->invoke($strategy, 'parseConstantStringExpression', $tokens, $tokenIndexes, $tokenOffsets, $content);

        $this->assertNull($result);
    }

    public function test_decode_string_token_edge_cases(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertSame('', $this->invoke($strategy, 'decodeStringToken', ''));
        $this->assertSame('foo', $this->invoke($strategy, 'decodeStringToken', '`foo`'));
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findTokenIndex(array $tokens, string $value): int
    {
        foreach ($tokens as $index => $token) {
            if (\is_array($token) && $token[1] === $value) {
                return $index;
            }
        }

        throw new \RuntimeException('Token not found: '.$value);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findNextToken(array $tokens, string $tokenValue, int $start): int
    {
        $count = \count($tokens);
        for ($i = $start + 1; $i < $count; $i++) {
            if ($tokens[$i] === $tokenValue) {
                return $i;
            }
        }

        throw new \RuntimeException('Token not found: '.$tokenValue);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<int, int>
     */
    private function buildTokenOffsets(array $tokens): array
    {
        $offsets = [];
        $offset = 0;

        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $text = \is_array($token) ? $token[1] : $token;
            $offset += \strlen($text);
        }

        return $offsets;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function tokensToContent(array $tokens): string
    {
        $content = '';
        foreach ($tokens as $token) {
            $content .= \is_array($token) ? $token[1] : $token;
        }

        return $content;
    }

    private function invoke(TokenBasedExtractionStrategy $strategy, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionClass($strategy);
        $refMethod = $ref->getMethod($method);

        return $refMethod->invoke($strategy, ...$args);
    }
}
