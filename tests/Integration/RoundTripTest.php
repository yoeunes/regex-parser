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

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Parser;

class RoundTripTest extends TestCase
{
    /**
     * @return array<string[]>
     */
    public static function providePatterns(): array
    {
        return [
            ['/abc/'],
            ['/^test$/i'],
            ['/[a-z0-9_-]+/'],
            ['/(?:foo|bar){1,2}?/s'],
            ['/(?<name>\w+)/'],
            ['/\\/home\\/user/'], // Echappement des délimiteurs
            ['#Hash matches#'],
            ['/\p{L}+/u'],
            ['/(?(1)foo|bar)/'],
        ];
    }

    /**
     * @dataProvider providePatterns
     */
    public function testParseAndCompileIsIdempotent(string $pattern): void
    {
        $parser = new Parser();
        $compiler = new CompilerNodeVisitor();

        $ast = $parser->parse($pattern);
        $compiled = $ast->accept($compiler);

        // Pour la plupart des cas simples, la chaîne doit être identique.
        // Attention: le compilateur peut normaliser certaines choses (ex: flags ordonnés ? non, pas actuellement)
        // Si le test échoue, vérifier si c'est une différence sémantique ou juste syntaxique.
        
        // Pour être flexible sur l'échappement qui peut varier (ex: / vs \/), on peut faire un check plus souple
        // ou simplement vérifier que le nouveau regex est valide.
        
        $this->assertNotNull(@preg_match($compiled, ''), "Compiled regex '$compiled' should be valid PHP PCRE");
        
        // Idéalement :
        $this->assertSame($pattern, $compiled);
    }
}
