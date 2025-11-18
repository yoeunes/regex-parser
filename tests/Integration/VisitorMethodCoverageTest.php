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
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

class VisitorMethodCoverageTest extends TestCase
{
    public function test_optimizer_leaf_nodes_return_same_instance(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        $nodes = [
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0),
            new DotNode(0, 0),
            new AnchorNode('^', 0, 0),
            new AssertionNode('b', 0, 0),
            new KeepNode(0, 0),
            new BackrefNode('1', 0, 0),
            new UnicodeNode('\x00', 0, 0),
            new UnicodePropNode('L', 0, 0),
            new OctalNode('\o{10}', 0, 0),
            new OctalLegacyNode('10', 0, 0),
            new PosixClassNode('alnum', 0, 0),
            new CommentNode('foo', 0, 0),
            new SubroutineNode('1', '', 0, 0),
            new PcreVerbNode('FAIL', 0, 0),
        ];

        foreach ($nodes as $node) {
            $result = $node->accept($optimizer);
            $this->assertSame($node, $result, \sprintf('Optimizer should return same instance for leaf node %s', $node::class));
        }
    }

    public function test_sample_generator_ignored_nodes_return_empty_string(): void
    {
        $generator = new SampleGeneratorVisitor();

        $nodes = [
            new AnchorNode('^', 0, 0),
            new AssertionNode('b', 0, 0),
            new KeepNode(0, 0),
            new CommentNode('foo', 0, 0),
            new PcreVerbNode('FAIL', 0, 0),
        ];

        foreach ($nodes as $node) {
            $result = $node->accept($generator);
            $this->assertSame('', $result, \sprintf('SampleGenerator should return empty string for node %s', $node::class));
        }
    }

    public function test_complexity_score_leaf_nodes(): void
    {
        $scorer = new ComplexityScoreVisitor();

        // Base score of 1
        $baseNodes = [
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0),
            new DotNode(0, 0),
            new AnchorNode('^', 0, 0),
            new AssertionNode('b', 0, 0),
            new KeepNode(0, 0),
            new UnicodeNode('x', 0, 0),
            new UnicodePropNode('L', 0, 0),
            new OctalNode('1', 0, 0),
            new OctalLegacyNode('1', 0, 0),
            new PosixClassNode('digit', 0, 0),
        ];

        foreach ($baseNodes as $node) {
            $this->assertSame(1, $node->accept($scorer));
        }

        // Zero score
        $this->assertSame(0, new CommentNode('', 0, 0)->accept($scorer));

        // Complex score (5)
        $this->assertSame(5, new BackrefNode('1', 0, 0)->accept($scorer));
        $this->assertSame(5, new PcreVerbNode('FAIL', 0, 0)->accept($scorer));
    }

    public function test_validator_leaf_nodes_valid(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new ValidatorNodeVisitor();

        $nodes = [
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0),
            new DotNode(0, 0),
            new AnchorNode('^', 0, 0),
            new CommentNode('foo', 0, 0),
        ];

        foreach ($nodes as $node) {
            // Should simply not throw
            $node->accept($validator);
        }
    }

    /**
     * Ce test force l'appel de chaque méthode visit*() pour chaque visiteur
     * avec des nœuds feuilles simples.
     */
    public function test_all_visitors_handle_all_leaf_nodes(): void
    {
        $nodes = [
            new AnchorNode('^', 0, 0),
            new AssertionNode('b', 0, 0),
            new BackrefNode('1', 0, 0),
            new CharTypeNode('d', 0, 0),
            new CommentNode('comment', 0, 0),
            new DotNode(0, 0),
            new KeepNode(0, 0),
            new LiteralNode('a', 0, 0),
            new OctalLegacyNode('0', 0, 0),
            new OctalNode('123', 0, 0),
            new PcreVerbNode('FAIL', 0, 0),
            new PosixClassNode('alnum', 0, 0),
            new SubroutineNode('1', '', 0, 0),
            new UnicodeNode('FFFF', 0, 0),
            new UnicodePropNode('L', 0, 0),
        ];

        $visitors = [
            new ExplainVisitor(),
            new HtmlExplainVisitor(),
            new OptimizerNodeVisitor(),
            new ComplexityScoreVisitor(),
            new ValidatorNodeVisitor(),
            new SampleGeneratorVisitor(),
        ];

        foreach ($visitors as $visitor) {
            foreach ($nodes as $node) {
                // SampleGenerator ne supporte pas les subroutines
                if ($visitor instanceof SampleGeneratorVisitor && $node instanceof SubroutineNode) {
                    continue;
                }

                // ValidatorNodeVisitor requires group context for backreferences
                if ($visitor instanceof ValidatorNodeVisitor && $node instanceof BackrefNode) {
                    continue;
                }

                // ValidatorNodeVisitor treats OctalLegacyNode('0') as invalid backreference \0
                if ($visitor instanceof ValidatorNodeVisitor && $node instanceof OctalLegacyNode) {
                    continue;
                }

                // ValidatorNodeVisitor requires group context for subroutines
                if ($visitor instanceof ValidatorNodeVisitor && $node instanceof SubroutineNode) {
                    continue;
                }

                $result = $node->accept($visitor);

                if ($visitor instanceof ValidatorNodeVisitor) {
                    // Le validateur retourne void (null)
                    $this->assertNull($result);
                } else {
                    // Les autres doivent retourner quelque chose (string, int, Node)
                    $this->assertNotNull($result, sprintf('Visitor %s returned null for node %s', $visitor::class, $node::class));
                }
            }
        }
    }

    public function test_sample_generator_throws_logic_exception_on_subroutine(): void
    {
        $visitor = new SampleGeneratorVisitor();
        $node = new SubroutineNode('R', '', 0, 0);

        $this->expectException(\LogicException::class);
        $node->accept($visitor);
    }
}
