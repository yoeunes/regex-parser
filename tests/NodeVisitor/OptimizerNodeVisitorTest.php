<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Parser;

class OptimizerNodeVisitorTest extends TestCase
{
    public function testMergeAdjacentLiterals(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/abc/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern);
        $this->assertSame('abc', $newAst->pattern->value);
    }

    public function testFlattenAlternations(): void
    {
        // On utilise des chaînes longues ("beta", "gamma") pour empêcher
        // l'optimiseur de convertir (b|c) en [bc] (CharClass).
        // On veut tester spécifiquement la fusion d'AlternationNode.

        $nestedAlt = new AlternationNode([
            new LiteralNode('beta', 0, 0),
            new LiteralNode('gamma', 0, 0),
        ], 0, 0);

        $rootAlt = new AlternationNode([
            new LiteralNode('alpha', 0, 0),
            $nestedAlt,
            new LiteralNode('delta', 0, 0),
        ], 0, 0);

        $optimizer = new OptimizerNodeVisitor();

        /** @var AlternationNode $newAst */
        $newAst = $rootAlt->accept($optimizer);

        // L'optimiseur doit avoir "remonté" beta et gamma au niveau racine -> 4 alternatives
        $this->assertCount(4, $newAst->alternatives);
        $this->assertInstanceOf(LiteralNode::class, $newAst->alternatives[1]);
        $this->assertSame('beta', $newAst->alternatives[1]->value);
    }

    public function testAlternationToCharClassOptimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|b|c/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(CharClassNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->parts);
    }

    public function testDigitOptimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/[0-9]/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(CharTypeNode::class, $newAst->pattern);
        $this->assertSame('d', $newAst->pattern->value);
    }

    public function testRemoveUselessNonCapturingGroup(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?:abc)/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern);
        $this->assertSame('abc', $newAst->pattern->value);
    }

    public function testQuantifierOptimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?:a)*/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(QuantifierNode::class, $newAst->pattern);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern->node);
    }

    public function testOptimizationDoesNotBreakSemanticsWithHyphen(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|-|z/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(AlternationNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->alternatives);
    }
}
