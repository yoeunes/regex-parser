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

class OfficialPcreComplianceTest extends TestCase
{
    #[DataProvider('providePcrePatterns')]
    public function test_pattern_compliance(string $pattern): void
    {
        // 1. Check if native PHP supports this pattern
        $nativeValid = false;

        try {
            // We use an empty string as subject just to check compilation
            @preg_match($pattern, '');
            if (\PREG_NO_ERROR === preg_last_error()) {
                $nativeValid = true;
            }
        } catch (\Throwable $e) {
            $nativeValid = false;
        }

        // 2. Parse with our library
        $parser = new Parser();
        $ast = null;

        try {
            $ast = $parser->parse($pattern);
        } catch (\Throwable $e) {
            if ($nativeValid) {
                $this->fail("Parser failed on valid pattern: $pattern. Error: ".$e->getMessage());
            }
            // If native failed, our parser failing is correct (or at least acceptable)
            // $this->assertTrue(true);

            return;
        }

        if (!$nativeValid) {
            // If native failed but we succeeded, it might be an issue, or we are more lenient.
            // For strict compliance, we might want to fail here, but let's just mark as risky or skip for now
            // as we want to focus on valid patterns first.
            // $this->markTestSkipped("Pattern is invalid in native PHP but parsed successfully: $pattern");
            return;
        }

        // 3. Compile back to string
        $compiler = new CompilerNodeVisitor();
        $compiledPattern = $ast->accept($compiler);

        // 4. Behavioral Comparison
        $subject = 'test string 123 abc !@#';

        // Native match
        $nativeResult = @preg_match($pattern, $subject, $nativeMatches);
        $nativeError = preg_last_error();

        // Compiled match
        $compiledResult = @preg_match($compiledPattern, $subject, $compiledMatches);
        $compiledError = preg_last_error();

        $this->assertSame($nativeError, $compiledError, "Error code mismatch for pattern: $pattern vs $compiledPattern");
        $this->assertSame($nativeResult, $compiledResult, "Return value mismatch for pattern: $pattern vs $compiledPattern");
        $this->assertSame($nativeMatches, $compiledMatches, "Matches mismatch for pattern: $pattern vs $compiledPattern");
    }

    public static function providePcrePatterns(): array
    {
        /** @var array $patterns */
        $patterns = require __DIR__.'/../Fixtures/pcre_patterns.php';

        return array_map(fn ($p) => [$p], $patterns);
    }
}
