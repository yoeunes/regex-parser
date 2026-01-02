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

namespace RegexParser\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Internal\PatternParser;

final class PatternParserTest extends TestCase
{
    public function test_extracts_flags_including_modifier_r_when_supported(): void
    {
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/a/r', 80400);

        $this->assertSame('a', $pattern);
        $this->assertSame('r', $flags);
        $this->assertSame('/', $delimiter);
    }

    public function test_rejects_modifier_r_when_target_php_is_older(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');

        PatternParser::extractPatternAndFlags('/a/r', 80300);
    }

    public function test_rejects_modifier_e_with_improved_message(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('The \'e\' flag (preg_replace /e) was removed; use preg_replace_callback.');

        PatternParser::extractPatternAndFlags('/a/e');
    }

    public function test_throws_for_missing_closing_delimiter(): void
    {
        $this->expectException(ParserException::class);
        PatternParser::extractPatternAndFlags('/abc');
    }

    public function test_supports_modifier_e_for_old_php_versions(): void
    {
        // Test that modifier 'e' is allowed when targeting PHP < 7.0
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/a/e', 50600); // PHP 5.6
        
        $this->assertSame('a', $pattern);
        $this->assertSame('e', $flags);
        $this->assertSame('/', $delimiter);
    }

    public function test_supports_modifier_r_runtime_detection(): void
    {
        // This test triggers the runtime detection code in supportsModifierR
        // On PHP 8.4+, this should work; on older versions, it should throw
        if (PHP_VERSION_ID >= 80400) {
            // PHP 8.4+ should support the 'r' modifier
            $result = PatternParser::extractPatternAndFlags('/a/r');
            $this->assertIsArray($result);
            $this->assertCount(3, $result);
        } else {
            // Older PHP versions should reject the 'r' modifier
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');
            PatternParser::extractPatternAndFlags('/a/r');
        }
    }

    public function test_supports_modifier_e_runtime_detection(): void
    {
        // This test triggers the runtime detection code in supportsModifierE
        // On PHP 7.0+, this should reject 'e' modifier
        if (PHP_VERSION_ID >= 70000) {
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('The \'e\' flag (preg_replace /e) was removed; use preg_replace_callback.');
        }
        
        // This call will trigger the runtime detection code
        PatternParser::extractPatternAndFlags('/a/e');
    }

    public function test_supports_modifier_r_runtime_detection_with_null_version(): void
    {
        // This test specifically targets the runtime detection code path
        // where phpVersionId is null (lines 137-143 in PatternParser)
        if (PHP_VERSION_ID >= 80400) {
            // PHP 8.4+ should support the 'r' modifier at runtime
            $result = PatternParser::extractPatternAndFlags('/a/r');
            $this->assertIsArray($result);
            $this->assertCount(3, $result);
            $this->assertSame('a', $result[0]);
            $this->assertSame('r', $result[1]);
        } else {
            // Older PHP versions should reject the 'r' modifier at runtime
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');
            PatternParser::extractPatternAndFlags('/a/r');
        }
    }

    public function test_supports_modifier_e_runtime_detection_with_null_version(): void
    {
        // This test specifically targets the runtime detection code path
        // where phpVersionId is null for modifier 'e'
        if (PHP_VERSION_ID >= 70000) {
            // PHP 7.0+ should reject the 'e' modifier at runtime
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('The \'e\' flag (preg_replace /e) was removed; use preg_replace_callback.');
        } else {
            // PHP < 7.0 should support the 'e' modifier at runtime
            $result = PatternParser::extractPatternAndFlags('/a/e');
            $this->assertIsArray($result);
            $this->assertCount(3, $result);
            $this->assertSame('a', $result[0]);
            $this->assertSame('e', $result[1]);
        }
        
        // This call will trigger the runtime detection code
        PatternParser::extractPatternAndFlags('/a/e');
    }

    public function test_supports_modifier_r_caching_behavior(): void
    {
        if (PHP_VERSION_ID >= 80400) {
            $result1 = PatternParser::extractPatternAndFlags('/a/r');
            $this->assertIsArray($result1);

            $result2 = PatternParser::extractPatternAndFlags('/b/r');
            $this->assertIsArray($result2);

            $this->assertSame('b', $result2[0]);
            $this->assertSame('r', $result2[1]);
        } else {
            $this->expectException(ParserException::class);
            PatternParser::extractPatternAndFlags('/a/r');
        }
    }

    public function test_supports_modifier_r_with_null_version_at_runtime(): void
    {
        $this->markTestSkipped('This test is replaced by test_supports_modifier_r_runtime_true and test_supports_modifier_r_runtime_false');
    }

    public function test_supports_modifier_r_runtime_true(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Only relevant for PHP < 8.4');
        }

        $result = PatternParser::extractPatternAndFlags('/a/r');
        $this->assertIsArray($result);
        $this->assertSame('a', $result[0]);
        $this->assertSame('r', $result[1]);
        $this->assertSame('/', $result[2]);
    }

    public function test_supports_modifier_r_runtime_false(): void
    {
        if (PHP_VERSION_ID >= 80400) {
            $this->markTestSkipped('Only relevant for PHP < 8.4');
        }

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');
        PatternParser::extractPatternAndFlags('/a/r');
    }

    public function test_supports_modifier_e_with_null_version_at_runtime(): void
    {
        $this->markTestSkipped('This test is replaced by test_supports_modifier_e_runtime_true and test_supports_modifier_e_runtime_false');
    }

    public function test_supports_modifier_e_runtime_true(): void
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('Only relevant for PHP < 7.0');
        }

        $result = PatternParser::extractPatternAndFlags('/a/e');
        $this->assertIsArray($result);
        $this->assertSame('a', $result[0]);
        $this->assertSame('e', $result[1]);
        $this->assertSame('/', $result[2]);
    }

    public function test_supports_modifier_e_runtime_false(): void
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped('Only relevant for PHP >= 7.0');
        }

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('The \'e\' flag (preg_replace /e) was removed; use preg_replace_callback.');
        PatternParser::extractPatternAndFlags('/a/e');
    }

    public function test_supports_modifier_r_with_specific_versions(): void
    {
        $this->assertSame(['a', 'r', '/'], PatternParser::extractPatternAndFlags('/a/r', 80400));
        $this->assertSame(['a', 'r', '/'], PatternParser::extractPatternAndFlags('/a/r', 80500));
        $this->assertSame(['a', 'r', '/'], PatternParser::extractPatternAndFlags('/a/r', 90000));

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');
        PatternParser::extractPatternAndFlags('/a/r', 80300);
    }

    public function test_supports_modifier_e_with_specific_versions(): void
    {
        $this->assertSame(['a', 'e', '/'], PatternParser::extractPatternAndFlags('/a/e', 50600));
        $this->assertSame(['a', 'e', '/'], PatternParser::extractPatternAndFlags('/a/e', 50500));

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('The \'e\' flag (preg_replace /e) was removed; use preg_replace_callback.');
        PatternParser::extractPatternAndFlags('/a/e', 70000);
    }
}
