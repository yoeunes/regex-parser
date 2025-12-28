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
use RegexParser\Lint\TokenBasedExtractionStrategy;
use RegexParser\Tests\Support\LintFunctionOverrides;

final class TokenBasedExtractionStrategyTokenHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        LintFunctionOverrides::reset();
    }

    public function test_is_namespaced_function_name_detects_qualified_call(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'Foo', 1],
            [\T_NS_SEPARATOR, '\\', 1],
            [\T_STRING, 'preg_match', 1],
        ];

        $this->assertTrue($this->invoke($strategy, 'isNamespacedFunctionName', $tokens, 2));
    }

    public function test_is_namespaced_function_name_returns_false_without_namespace(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'preg_match', 1],
        ];

        $this->assertFalse($this->invoke($strategy, 'isNamespacedFunctionName', $tokens, 0));
    }

    public function test_is_namespaced_function_name_returns_false_with_non_separator(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_STRING, 'Foo', 1],
            [\T_STRING, 'preg_match', 1],
        ];

        $this->assertFalse($this->invoke($strategy, 'isNamespacedFunctionName', $tokens, 1));
    }

    public function test_is_namespaced_function_name_returns_false_without_prefix_token(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_NS_SEPARATOR, '\\', 1],
            [\T_STRING, 'preg_match', 1],
        ];

        $this->assertFalse($this->invoke($strategy, 'isNamespacedFunctionName', $tokens, 1));
    }

    public function test_is_name_token_handles_known_types(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertTrue($this->invoke($strategy, 'isNameToken', [\T_STRING, 'Foo', 1]));

        if (\defined('T_NAME_QUALIFIED')) {
            $this->assertTrue($this->invoke($strategy, 'isNameToken', [\T_NAME_QUALIFIED, 'Foo\\Bar', 1]));
        }

        if (\defined('T_NAME_FULLY_QUALIFIED')) {
            $this->assertTrue($this->invoke($strategy, 'isNameToken', [\T_NAME_FULLY_QUALIFIED, '\\Foo\\Bar', 1]));
        }

        if (\defined('T_NAME_RELATIVE')) {
            $this->assertTrue($this->invoke($strategy, 'isNameToken', [\T_NAME_RELATIVE, 'namespace\\Foo', 1]));
        }

        $this->assertFalse($this->invoke($strategy, 'isNameToken', [\T_VARIABLE, '$foo', 1]));
    }

    public function test_read_name_token_returns_null_for_string_token(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertNull($this->invoke($strategy, 'readNameToken', 'foo'));
    }

    public function test_read_name_token_handles_name_variants(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        if (\defined('T_NAME_FULLY_QUALIFIED')) {
            $this->assertSame('\\Foo\\Bar', $this->invoke($strategy, 'readNameToken', [\T_NAME_FULLY_QUALIFIED, '\\Foo\\Bar', 1]));
        }

        if (\defined('T_NAME_RELATIVE')) {
            $this->assertSame('namespace\\Foo', $this->invoke($strategy, 'readNameToken', [\T_NAME_RELATIVE, 'namespace\\Foo', 1]));
        }
    }

    public function test_is_double_arrow_token_accepts_string(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertTrue($this->invoke($strategy, 'isDoubleArrowToken', '=>'));
    }

    public function test_next_and_previous_significant_token_index_return_null(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        $tokens = [
            [\T_WHITESPACE, ' ', 1],
            [\T_COMMENT, '//', 1],
        ];

        $this->assertNull($this->invoke($strategy, 'nextSignificantTokenIndex', $tokens, 0, \count($tokens)));
        $this->assertNull($this->invoke($strategy, 'previousSignificantTokenIndex', $tokens, \count($tokens) - 1));
    }

    public function test_closing_token_for_brace(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertSame('}', $this->invoke($strategy, 'closingTokenFor', '{'));
    }

    public function test_strip_outer_parentheses_branches(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertSame([], $this->invoke($strategy, 'stripOuterParentheses', []));

        $tokens = ['(', 'a', ')'];
        $this->assertSame(['a'], $this->invoke($strategy, 'stripOuterParentheses', $tokens));

        $tokens = [
            [\T_WHITESPACE, ' ', 1],
        ];
        $this->assertSame($tokens, $this->invoke($strategy, 'stripOuterParentheses', $tokens));

        $tokens = ['(', 'a', ')', '(', 'b', ')'];
        $this->assertSame($tokens, $this->invoke($strategy, 'stripOuterParentheses', $tokens));
    }

    public function test_find_array_start_index_variants(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tokens = ['[', [\T_CONSTANT_ENCAPSED_STRING, "'/a/'", 1], ']'];
        $this->assertSame(0, $this->invoke($strategy, 'findArrayStartIndex', $tokens));

        $tokens = [
            [\T_ARRAY, 'array', 1],
            '(',
            [\T_CONSTANT_ENCAPSED_STRING, "'/a/'", 1],
            ')',
        ];
        $this->assertSame(1, $this->invoke($strategy, 'findArrayStartIndex', $tokens));

        $tokens = [[\T_STRING, 'foo', 1]];
        $this->assertNull($this->invoke($strategy, 'findArrayStartIndex', $tokens));

        $tokens = [
            [\T_WHITESPACE, ' ', 1],
        ];
        $this->assertNull($this->invoke($strategy, 'findArrayStartIndex', $tokens));
    }

    public function test_ensure_valid_utf8_handles_null_and_conversion(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $this->assertSame('hello', $this->invoke($strategy, 'ensureValidUtf8', 'hello'));
        $this->assertNull($this->invoke($strategy, 'ensureValidUtf8', "a\0b"));

        $latin1 = "\xE9";
        $converted = $this->invoke($strategy, 'ensureValidUtf8', $latin1);
        $this->assertIsString($converted);

        $latin1WithNull = "\xE9\0";
        $this->assertNull($this->invoke($strategy, 'ensureValidUtf8', $latin1WithNull));
    }

    public function test_ensure_valid_utf8_returns_null_when_conversion_fails(): void
    {
        LintFunctionOverrides::$mbCheckEncodingResult = false;
        LintFunctionOverrides::$mbConvertEncodingResult = false;

        $strategy = new TokenBasedExtractionStrategy();

        $this->assertNull($this->invoke($strategy, 'ensureValidUtf8', 'data'));
    }

    private function invoke(TokenBasedExtractionStrategy $strategy, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionClass($strategy);
        $refMethod = $ref->getMethod($method);

        return $refMethod->invoke($strategy, ...$args);
    }
}
