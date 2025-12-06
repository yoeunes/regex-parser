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

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class ManualFallbackTest extends TestCase
{
    /**
     * Teste le fallback du SampleGenerator pour un type de caractère inconnu.
     * Impossible via le parser car il rejette les types inconnus.
     */
    public function test_sample_generator_unknown_char_type(): void
    {
        // On injecte un nœud avec un type invalide 'z'
        $node = new CharTypeNode('z', 0, 0);
        $generator = new SampleGeneratorNodeVisitor();

        // Doit retourner '?' (le default du switch)
        $this->assertSame('?', $node->accept($generator));
    }

    /**
     * Teste le fallback du Compiler pour une syntaxe de subroutine inconnue.
     * Le parser normalise les syntaxes, donc on injecte un nœud manuel.
     */
    public function test_compiler_subroutine_default_syntax(): void
    {
        // Syntaxe vide '' déclenche le default dans visitSubroutine
        $node = new SubroutineNode('1', 'UNKNOWN_SYNTAX', 0, 0);
        $compiler = new CompilerNodeVisitor();

        // Le default retourne '(?reference)'
        $this->assertSame('(?1)', $node->accept($compiler));
    }

    /**
     * Teste le fallback de la méthode privée parseQuantifierRange dans SampleGeneratorVisitor
     * via Reflection, pour un quantifieur inconnu.
     */
    public function test_sample_generator_parse_quantifier_fallback(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('parseQuantifierRange');

        // Appel avec un quantifieur invalide qui ne matche aucun cas
        $result = $method->invoke($generator, 'INVALID');

        // Le default retourne [0, 0] (via @codeCoverageIgnore, mais testons-le quand même)
        $this->assertSame([0, 0], $result);
    }
}
