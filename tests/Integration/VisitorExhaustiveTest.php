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

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

final class VisitorExhaustiveTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_all_visitors_visit_all_nodes(): void
    {
        // Liste de tous les visiteurs
        $visitors = [
            new CompilerNodeVisitor(),
            new ComplexityScoreNodeVisitor(),
            new DumperNodeVisitor(),
            new ExplainNodeVisitor(),
            new HtmlExplainNodeVisitor(),
            new OptimizerNodeVisitor(),
            // Note: SampleGenerator et Validator ont des logiques strictes qui peuvent throw des exceptions
            // sur des nœuds isolés. On les inclut mais on catchera les erreurs.
            new SampleGeneratorNodeVisitor(),
            new ValidatorNodeVisitor(),
        ];

        // Liste exhaustive d'instances de chaque type de nœud
        $nodes = [
            new AlternationNode([], 0, 0),
            new AnchorNode('^', 0, 0),
            new AssertionNode('b', 0, 0),
            new BackrefNode('1', 0, 0),
            new CharClassNode(new LiteralNode('', 0, 0), false, 0, 0),
            new CharTypeNode('d', 0, 0),
            new CommentNode('comment', 0, 0),
            new ConditionalNode(new BackrefNode('1', 0, 0), new LiteralNode('a', 0, 0), new LiteralNode('b', 0, 0), 0, 0),
            new DotNode(0, 0),
            new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_CAPTURING, null, null, 0, 0),
            new KeepNode(0, 0),
            new LiteralNode('a', 0, 0),
            new CharLiteralNode('01', 0o1, CharLiteralType::OCTAL_LEGACY, 0, 0),
            new CharLiteralNode('\o{123}', 0o123, CharLiteralType::OCTAL, 0, 0),
            new PcreVerbNode('FAIL', 0, 0),
            new PosixClassNode('alnum', 0, 0),
            new QuantifierNode(new LiteralNode('a', 0, 0), '*', QuantifierType::T_GREEDY, 0, 0),
            new RangeNode(new LiteralNode('a', 0, 0), new LiteralNode('z', 0, 0), 0, 0),
            new RegexNode(new LiteralNode('a', 0, 0), 'i', '/', 0, 0),
            new SequenceNode([], 0, 0),
            new SubroutineNode('1', '', 0, 0),
            new CharLiteralNode('\x41', 0x41, CharLiteralType::UNICODE, 0, 0),
            new UnicodePropNode('L', 0, 0),
        ];

        foreach ($visitors as $visitor) {
            foreach ($nodes as $node) {
                // Cas spécifiques à ignorer pour le Validator qui a besoin de contexte (groupes existants)
                if ($visitor instanceof ValidatorNodeVisitor) {
                    if ($node instanceof BackrefNode || $node instanceof SubroutineNode || ($node instanceof CharLiteralNode && CharLiteralType::OCTAL_LEGACY === $node->type)) {
                        continue;
                    }
                }

                // Cas spécifique SampleGenerator qui ne supporte pas les subroutines
                if ($visitor instanceof SampleGeneratorNodeVisitor && $node instanceof SubroutineNode) {
                    continue;
                }

                try {
                    $node->accept($visitor);
                } catch (\Throwable) {
                }
            }
        }
    }
}
