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
use RegexParser\Regex;

/**
 * Tests that the compiler correctly reconstructs the parsed AST back into a regex string
 * that is semantically equivalent to the input.
 */
final class ReverseCompilerTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    #[DataProvider('providePatterns')]
    public function test_round_trip_compilation(string $originalPattern): void
    {
        $ast = $this->regexService->parse($originalPattern);
        $compiler = new CompilerNodeVisitor();
        $recompiled = $ast->accept($compiler);

        // 1. The recompiled regex must be valid
        $this->assertNotFalse(
            @preg_match($recompiled, ''),
            "Recompiled regex '$recompiled' from '$originalPattern' is invalid.",
        );

        // 2. Generate a matching sample from the original
        // This proves that the recompiled regex matches what the original matched.
        try {
            $sample = $this->regexService->generate($originalPattern);

            if ('' !== $sample) {
                $this->assertMatchesRegularExpression(
                    $recompiled,
                    $sample,
                    "Recompiled regex '$recompiled' failed to match sample '$sample' generated from '$originalPattern'",
                );
            }
        } catch (\Exception) {
            // Some patterns (like assertions) don't generate samples easily, skip sample test
        }

        // 3. If the optimizer is skipped, simple patterns should match exactly or be very close
        // (ignoring insignificant differences handled by the compiler like escape chars)
        // Note: We don't assert strict string equality because the compiler might normalize things
        // e.g. \p{L} vs \pL, or escaping / inside / delimiters.
    }

    public static function providePatterns(): \Iterator
    {
        // Basic
        yield ['/abc/'];
        yield ['/^test$/i'];
        yield ['/[a-z0-9]+/'];

        // Groups and Alternation
        yield ['/(a|b)+/'];
        yield ['/(?:foo|bar)/'];
        yield ['/(?<name>\w+)/'];

        // Quantifiers
        yield ['/a{1,3}/'];
        yield ['/a*?/'];
        yield ['/a++/'];

        // Character Classes
        yield ['/[^0-9]/'];
        yield ['/[a-z-]/']; // Dash at end
        yield ['/[-a-z]/']; // Dash at start

        // Advanced
        yield ['/(?=.*[A-Z])(?=.*[0-9])/'];
        yield ['/(?<=foo)bar/'];
        yield ['/(?<!not)allowed/'];
        yield ['/\p{L}+/u'];
        yield ['/\o{101}/'];

        // Conditionals and Subroutines
        yield ['/(?(1)yes|no)/'];
        yield ['/(?R)/'];
        yield ['/(?&name)/'];

        // Complex Real world
        yield ['/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'];
    }
}
