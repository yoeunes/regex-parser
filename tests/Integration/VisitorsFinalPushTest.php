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
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class VisitorsFinalPushTest extends TestCase
{
    /**
     * Teste la branche "if ($optimizedPart !== $part)" dans OptimizerNodeVisitor::visitCharClass.
     * Normalement, les enfants d'une classe de caractères ne changent pas.
     * On utilise un Mock pour forcer un changement.
     */
    public function test_optimizer_detects_change_in_char_class_parts(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // On crée un Mock de nœud qui retourne une NOUVELLE instance quand on le visite
        $mockPart = $this->createMock(NodeInterface::class);
        $mockPart->expects($this->once())
            ->method('accept')
            ->willReturn(new LiteralNode('changed', 0, 0));

        // On l'injecte dans une CharClassNode
        $node = new CharClassNode([$mockPart], false, 0, 0);

        // On visite
        $result = $node->accept($optimizer);

        // Le résultat doit être une nouvelle CharClassNode (pas la même instance)
        $this->assertNotSame($node, $result);
        $this->assertInstanceOf(CharClassNode::class, $result);
        $this->assertInstanceOf(LiteralNode::class, $result->parts[0]);
        $this->assertSame('changed', $result->parts[0]->value);
    }

    /**
     * Teste le fallback "default" du SampleGenerator pour un CharType inconnu.
     * (Impossible via le parser car il valide les types, donc on injecte manuellement).
     */
    public function test_sample_generator_unknown_char_type(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        // 'Z' n'est pas un type standard connu du générateur
        $node = new CharTypeNode('Z', 0, 0);

        $result = $node->accept($generator);

        // Doit retourner '?' (le default)
        $this->assertSame('?', $result);
    }
}
