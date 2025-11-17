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
            // Le compilateur échappe le tiret pour la sécurité, on s'attend donc à une différence
            ['/[a-z0-9_-]+/', '/[a-z0-9_\-]+/'], 
            ['/(?:foo|bar){1,2}?/s'],
            ['/(?<name>\w+)/'],
            ['/\\/home\\/user/'], 
            ['#Hash matches#'],
            // Le compilateur normalise \p{L} en \pL
            ['/\p{L}+/u', '/\pL+/u'], 
            ['/(?(1)foo|bar)/'],
        ];
    }

    #[DataProvider('providePatterns')]
    public function testParseAndCompileIsIdempotent(string $pattern, ?string $expected = null): void
    {
        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        // Si une version "attendue" est fournie (car normalisée), on l'utilise.
        // Sinon, on s'attend à ce que la sortie soit identique à l'entrée.
        $this->assertSame($expected ?? $pattern, $compiled);
        
        // Vérification de sécurité : la regex générée doit toujours être valide
        $this->assertNotFalse(@preg_match($compiled, ''), "Compiled regex '$compiled' is invalid");
    }
}
