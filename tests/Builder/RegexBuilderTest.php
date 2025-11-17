<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RegexParser\Builder\RegexBuilder;

class RegexBuilderTest extends TestCase
{
    public function testBuildSimpleSequence(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->startOfLine()
            ->literal('http')
            ->optional()
            ->literal('://')
            ->any()
            ->oneOrMore()
            ->endOfLine()
            ->compile();

        // Le builder échappe automatiquement les littéraux, donc / devient \/ sauf si le délimiteur change?
        // Ton compilateur actuel n'échappe pas '/' par défaut si ce n'est pas le délimiteur.
        // Vérifions le résultat attendu.
        
        // ^http?://.+$
        // Note: tes literals échappent tout meta char. : et / ne sont pas meta.
        $this->assertSame('/^http?:\/\/.+$/', $regex);
    }

    public function testBuildAlternation(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->literal('cat')
            ->or
            ->literal('dog')
            ->compile();

        $this->assertSame('/cat|dog/', $regex);
    }

    public function testBuildCharClass(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->charClass(function ($c) {
                $c->range('a', 'z')
                  ->digit();
            })
            ->oneOrMore()
            ->compile();

        $this->assertSame('/[a-z\d]+/', $regex);
    }

    public function testBuildNamedGroup(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->namedGroup('id', function ($b) {
                $b->digit()->oneOrMore();
            })
            ->withFlags('i')
            ->compile();

        $this->assertSame('/(?<id>\d+)/i', $regex);
    }
    
    public function testSafeEscapingInLiteral(): void
    {
        $builder = new RegexBuilder();
        // literal() doit échapper les caractères spéciaux
        $regex = $builder->literal('a.b*c')->compile();
        
        $this->assertSame('/a\.b\*c/', $regex);
    }
}
