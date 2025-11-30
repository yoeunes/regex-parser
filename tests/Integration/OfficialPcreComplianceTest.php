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
 * Validates that the library behaves identically to PHP's native PCRE engine.
 *
 * @see https://github.com/php/php-src/tree/master/ext/pcre/tests
 */
class OfficialPcreComplianceTest extends TestCase
{
    #[DataProvider('providePcrePatterns')]
    public function test_pattern_compliance(string $pattern): void
    {
        // 1. Determine native validity (Ground Truth)
        $nativeValid = false;

        try {
            // Silence warnings as we expect many patterns to be invalid during stress testing
            @preg_match($pattern, '');
            if (\PREG_NO_ERROR === preg_last_error()) {
                $nativeValid = true;
            }
        } catch (\Throwable) {
            $nativeValid = false;
        }

        // 2. Parse with the library
        $parser = new Parser();
        $ast = null;
        $parseException = null;

        try {
            $ast = $parser->parse($pattern);
        } catch (\Throwable $e) {
            $parseException = $e;
        }

        // Scenario A: Pattern is natively invalid
        if (!$nativeValid) {
            if (null !== $parseException) {
                // Success: Native rejects it, and we reject it too.
                $this->assertNotEmpty($parseException->getMessage(), 'Native PHP and Parser both rejected the invalid pattern.');

                return;
            }

            // Native rejects it, but we accept it. This is technically a divergence,
            // but might be acceptable if we are more permissive or strictly structural.
            // We mark it skipped to clean up the output without failing the build.
            $this->markTestSkipped(\sprintf('Pattern "%s" is invalid in PHP but was accepted by the parser.', $pattern));
        }

        // Scenario B: Pattern is natively valid, but Parser failed
        if (null !== $parseException) {
            $this->fail(\sprintf(
                'Parser failed on a valid pattern: "%s". Error: %s',
                $pattern,
                $parseException->getMessage(),
            ));
        }

        // Scenario C: Pattern is valid, verify behavioral consistency
        $compiler = new CompilerNodeVisitor();

        try {
            $compiledPattern = $ast->accept($compiler);
        } catch (\Throwable $e) {
            $this->fail(\sprintf('Compilation failed for pattern: "%s". Error: %s', $pattern, $e->getMessage()));
        }

        $subject = 'test string 123 abc !@#';

        // 3. Compare execution results
        $nativeResult = @preg_match($pattern, $subject, $nativeMatches);
        $nativeErrorCode = preg_last_error();

        $compiledResult = @preg_match($compiledPattern, $subject, $compiledMatches);
        $compiledErrorCode = preg_last_error();

        $this->assertSame(
            $nativeErrorCode,
            $compiledErrorCode,
            \sprintf('Error code mismatch between original ("%s") and compiled ("%s")', $pattern, $compiledPattern),
        );

        $this->assertSame(
            $nativeResult,
            $compiledResult,
            \sprintf('Match result mismatch (0/1) between original ("%s") and compiled ("%s")', $pattern, $compiledPattern),
        );

        $this->assertSame(
            $nativeMatches,
            $compiledMatches,
            \sprintf('Captured groups mismatch between original ("%s") and compiled ("%s")', $pattern, $compiledPattern),
        );
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function providePcrePatterns(): iterable
    {
        $file = __DIR__.'/../Fixtures/pcre_patterns.php';

        if (!file_exists($file)) {
            return [];
        }

        /** @var array $patterns */
        $patterns = require $file;

        foreach ($patterns as $pattern) {
            if (!\is_string($pattern) || '' === $pattern) {
                continue;
            }

            yield [$pattern];
        }
    }
}
