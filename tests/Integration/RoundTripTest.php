<?php

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
            // Le compilateur échappe le tiret pour la sécurité
            ['/[a-z0-9_-]+/', '/[a-z0-9_\-]+/'],
            ['/(?:foo|bar){1,2}?/s'],
            ['/(?<name>\w+)/'],
            ['/\\/home\\/user/'],
            ['#Hash matches#'],
            // Le compilateur normalise \p{L} en \pL
            ['/\p{L}+/u', '/\pL+/u'],
            // Correction ici : On définit le groupe 1 (a) pour que la condition (?(1)...) soit valide
            ['/(a)(?(1)b|c)/'],
        ];
    }

    #[DataProvider('providePatterns')]
    public function testParseAndCompileIsIdempotent(string $pattern, ?string $expected = null): void
    {
        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        $this->assertSame($expected ?? $pattern, $compiled);

        // Cette assertion échouait car la regex n'avait pas de groupe 1
        $this->assertNotFalse(@preg_match($compiled, ''), "Compiled regex '$compiled' is invalid");
    }
}
