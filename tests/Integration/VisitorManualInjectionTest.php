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
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

class VisitorManualInjectionTest extends TestCase
{
    public function test_optimizer_handles_empty_alternation(): void
    {
        // Cas impossible via parser : AlternationNode vide
        $node = new AlternationNode([], 0, 0);
        $optimizer = new OptimizerNodeVisitor();

        // Devrait retourner le nœud tel quel ou ne pas crasher
        $result = $node->accept($optimizer);
        $this->assertSame($node, $result); // ou assertion selon ta logique
    }

    public function test_compiler_handles_literal_bracket_outside_char_class(): void
    {
        // Le parser gère ']' comme littéral s'il n'y a pas de '[' ouvert,
        // mais testons explicitement que le compilateur ne l'échappe pas inutilement
        $node = new LiteralNode(']', 0, 0);
        $compiler = new CompilerNodeVisitor();

        $this->assertSame(']', $node->accept($compiler));
    }

    public function test_sample_generator_fallback_on_empty_char_class(): void
    {
        // Une classe vide [] est normalement une erreur de parsing,
        // mais si on la construit manuellement :
        $node = new CharClassNode([], false, 0, 0);
        $generator = new SampleGeneratorNodeVisitor();

        $this->expectException(\RuntimeException::class); // Ou le comportement attendu
        $node->accept($generator);
    }

    public function test_compiler_quantifier_on_alternation_adds_non_capturing_group(): void
    {
        // (a|b)* -> le compilateur doit ajouter (?:...) autour de a|b
        $alt = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('b', 0, 0)
        ], 0, 0);

        $quantifier = new QuantifierNode($alt, '*', QuantifierType::T_GREEDY, 0, 0);
        $compiler = new CompilerNodeVisitor();

        // Doit produire (?:a|b)*
        $this->assertSame('(?:a|b)*', $quantifier->accept($compiler));
    }
}
