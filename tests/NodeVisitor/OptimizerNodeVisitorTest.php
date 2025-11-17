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
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Parser;

class OptimizerNodeVisitorTest extends TestCase
{
    private function optimize(string $regex): string
    {
        // On parse, on optimise l'AST, puis on le recompile via le Compiler (implicite dans tes autres tests)
        // Ici on va inspecter les noeuds résultants pour être sûr de la structure
        return ''; // Helper placeholder, we allow direct AST inspection below
    }

    public function testMergeAdjacentLiterals(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a.b.c/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);
        $sequence = $newAst->pattern;

        // "a", ".", "b", ".", "c" -> Devrait rester tel quel car les points séparent
        // Mais "/abc/" -> Sequence(Literal("abc"))

        $ast2 = $parser->parse('/abc/');
        $newAst2 = $ast2->accept($optimizer);

        // L'optimiseur devrait avoir fusionné a, b, c en un seul LiteralNode
        $this->assertInstanceOf(LiteralNode::class, $newAst2->pattern);
        $this->assertSame('abc', $newAst2->pattern->value);
    }

    public function testFlattenAlternations(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|(b|c)|d/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);
        $alternation = $newAst->pattern;

        // Devrait être aplati en a|b|c|d
        $this->assertCount(4, $alternation->alternatives);
        $this->assertSame('b', $alternation->alternatives[1]->value);
    }

    public function testAlternationToCharClassOptimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|b|c/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        // Doit devenir [abc]
        $this->assertInstanceOf(CharClassNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->parts);
    }

    public function testDigitOptimization(): void
    {
        $parser = new Parser();
        // [0-9] -> \d
        $ast = $parser->parse('/[0-9]/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(CharTypeNode::class, $newAst->pattern);
        $this->assertSame('d', $newAst->pattern->value);
    }

    public function testWordOptimization(): void
    {
        $parser = new Parser();
        // [a-zA-Z0-9_] -> \w
        $ast = $parser->parse('/[a-zA-Z0-9_]/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(CharTypeNode::class, $newAst->pattern);
        $this->assertSame('w', $newAst->pattern->value);
    }

    public function testRemoveUselessNonCapturingGroup(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?:abc)/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        // Le groupe doit disparaitre, ne laissant que le Literal
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern);
        $this->assertSame('abc', $newAst->pattern->value);
    }

    public function testQuantifierOptimization(): void
    {
        $parser = new Parser();
        // (?:a)* -> a*
        $ast = $parser->parse('/(?:a)*/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(QuantifierNode::class, $newAst->pattern);
        // Le noeud enfant ne doit PLUS être un groupe, mais direct le literal
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern->node);
    }

    public function testOptimizationDoesNotBreakSemanticsWithHyphen(): void
    {
        // Test critique pour le bug potentiel du compilateur/optimiseur
        $parser = new Parser();
        // a|-|z -> [a-z] serait FAUX. Ça doit être [a\-z] ou rester une alternation si pas sûr.
        $ast = $parser->parse('/a|-|z/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        // Si transformé en CharClass, on doit vérifier que le compilateur le gère.
        // Mais ici on vérifie juste que l'optimiseur fait son job.
        if ($newAst->pattern instanceof CharClassNode) {
            $parts = $newAst->pattern->parts;
            // On s'attend à voir le tiret comme un LiteralNode
            $this->assertSame('-', $parts[1]->value);
        }
    }
}
