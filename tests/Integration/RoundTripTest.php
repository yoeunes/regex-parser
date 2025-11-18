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

class RoundTripTest extends TestCase
{
    /**
     * @return array<array{0: string, 1?: string}>
     */
    public static function providePatterns(): array
    {
        return [
            ['/abc/'],
            ['/^test$/i'],
            // The compiler escapes the hyphen for safety
            ['/[a-z0-9_-]+/', '/[a-z0-9_\-]+/'],
            ['/(?:foo|bar){1,2}?/s'],
            ['/(?<name>\w+)/'],
            ['/\\/home\\/user/'],
            ['#Hash matches#'],
            // The compiler normalizes \p{L} to \pL
            ['/\p{L}+/u', '/\pL+/u'],
            // Correction here: We define group 1 (a) so that the condition (?(1)...) is valid
            ['/(a)(?(1)b|c)/'],
        ];
    }

    #[DataProvider('providePatterns')]
    public function test_parse_and_compile_is_idempotent(string $pattern, ?string $expected = null): void
    {
        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        $this->assertSame($expected ?? $pattern, $compiled);

        // This assertion was failing because the regex didn't have group 1
        $this->assertNotFalse(@preg_match($compiled, ''), "Compiled regex '$compiled' is invalid");
    }
}
