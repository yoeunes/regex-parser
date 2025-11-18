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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Parser;

class OptimizerNodeVisitorTest extends TestCase
{
    public function test_merge_adjacent_literals(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/abc/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern);
        $this->assertSame('abc', $newAst->pattern->value);
    }

    public function test_flatten_alternations(): void
    {
        // We use long strings ("beta", "gamma") to prevent
        // the optimizer from converting (b|c) into [bc] (CharClass).
        // We specifically want to test the merging of AlternationNode.

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

        // The optimizer should have "lifted" beta and gamma to the root level -> 4 alternatives
        $this->assertCount(4, $newAst->alternatives);
        $this->assertInstanceOf(LiteralNode::class, $newAst->alternatives[1]);
        $this->assertSame('beta', $newAst->alternatives[1]->value);
    }

    public function test_alternation_to_char_class_optimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|b|c/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(CharClassNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->parts);
    }

    public function test_digit_optimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/[0-9]/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(CharTypeNode::class, $newAst->pattern);
        $this->assertSame('d', $newAst->pattern->value);
    }

    public function test_remove_useless_non_capturing_group(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?:abc)/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern);
        $this->assertSame('abc', $newAst->pattern->value);
    }

    public function test_quantifier_optimization(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?:a)*/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(QuantifierNode::class, $newAst->pattern);
        $this->assertInstanceOf(LiteralNode::class, $newAst->pattern->node);
    }

    public function test_optimization_does_not_break_semantics_with_hyphen(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a|-|z/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(AlternationNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->alternatives);
    }

    public function test_merge_adjacent_literals_and_sequences(): void
    {
        // Pattern: /a(b)(c)(d(e)f)/
        // Devrait être optimisé en: /a b c d e f/ (LiteralNode)

        $parser = new Parser();
        $ast = $parser->parse('/abc/');
        $optimizer = new OptimizerNodeVisitor();

        // Simuler un AST plus complexe pour tester la fusion de LiteralNode adjacents
        $rawAst = new RegexNode(
            new SequenceNode([
                new LiteralNode('a', 0, 1),
                new LiteralNode('b', 1, 2),
                new SequenceNode([ // Séquence imbriquée
                    new LiteralNode('c', 2, 3),
                    new LiteralNode('d', 3, 4),
                ], 2, 4),
                new LiteralNode('e', 4, 5),
            ], 0, 5),
            '', '/', 0, 5,
        );

        $optimizedAst = $rawAst->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $optimizedAst);
        $this->assertInstanceOf(LiteralNode::class, $optimizedAst->pattern);
        $this->assertSame(
            'abcde',
            $optimizedAst->pattern->value,
            'Adjacent literals and flattened sequence should merge.',
        );
    }

    public function test_remove_empty_literal_from_sequence(): void
    {
        // Séquence contenant un nœud vide (qui pourrait venir d'un groupe vide)
        $rawAst = new RegexNode(
            new SequenceNode([
                new LiteralNode('x', 0, 1),
                new LiteralNode('', 1, 1), // Nœud vide à supprimer
                new LiteralNode('y', 1, 2),
            ], 0, 2),
            '', '/', 0, 2,
        );

        $optimizer = new OptimizerNodeVisitor();
        $optimizedAst = $rawAst->accept($optimizer);

        $this->assertInstanceOf(RegexNode::class, $optimizedAst);
        $this->assertInstanceOf(LiteralNode::class, $optimizedAst->pattern);
        $this->assertSame('xy', $optimizedAst->pattern->value, 'Empty literal should be removed and remaining merged.');
    }

    public function test_alternation_to_char_class_with_hyphen_as_literal(): void
    {
        // a|-|z ne devrait PAS devenir [a-z] car le '-' est un littéral, pas une partie d'un range.
        $parser = new Parser();
        $ast = $parser->parse('/a|-|z/');
        $optimizer = new OptimizerNodeVisitor();

        $newAst = $ast->accept($optimizer);

        // Le résultat devrait rester une AlternationNode si l'optimisation en CharClass échoue
        $this->assertInstanceOf(RegexNode::class, $newAst);
        $this->assertInstanceOf(AlternationNode::class, $newAst->pattern);
        $this->assertCount(3, $newAst->pattern->alternatives);
    }

    // ... (tests existants: test_merge_adjacent_literals, test_flatten_alternations, test_alternation_to_char_class_optimization, test_digit_optimization, test_remove_useless_non_capturing_group, test_quantifier_optimization, test_optimization_does_not_break_semantics_with_hyphen)

    public function test_return_original_node_if_no_change_in_regex_node(): void
    {
        $pattern = new LiteralNode('a', 0, 1);
        $originalAst = new RegexNode($pattern, 'i', '/', 0, 3);
        $optimizer = new OptimizerNodeVisitor();

        // Le pattern est un LiteralNode simple, l'optimizer ne fait rien dessus.
        $optimizedAst = $originalAst->accept($optimizer);

        $this->assertSame(
            $originalAst,
            $optimizedAst,
            'Should return the same object instance if pattern is unchanged.',
        );
    }

    public function test_char_class_word_optimization_unicode_flag_present(): void
    {
        // [a-zA-Z0-9_] -> \w, mais PAS si le flag 'u' est présent
        $parser = new Parser();
        $ast = $parser->parse('/[a-zA-Z0-9_]+/u'); // flag 'u' est présent
        $optimizer = new OptimizerNodeVisitor();

        $optimizedAst = $ast->accept($optimizer);

        // Le pattern devrait rester CharClassNode, pas CharTypeNode('\w')
        $this->assertInstanceOf(RegexNode::class, $optimizedAst);
        $this->assertInstanceOf(QuantifierNode::class, $optimizedAst->pattern);
        $this->assertInstanceOf(
            CharClassNode::class,
            $optimizedAst->pattern->node,
            'Should not optimize to \w due to /u flag.',
        );
    }

    public function test_full_word_optimization(): void
    {
        // [a-zA-Z0-9_] -> \w
        $parser = new Parser();
        $ast = $parser->parse('/[a-zA-Z0-9_]/');
        $optimizer = new OptimizerNodeVisitor();

        /** @var RegexNode $newAst */
        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(CharTypeNode::class, $newAst->pattern);
        $this->assertSame('w', $newAst->pattern->value);
    }

    public function test_does_not_optimize_word_if_flag_u_present(): void
    {
        // [a-zA-Z0-9_] -> NOT \w if /u is present (semantics change in PCRE)
        $parser = new Parser();
        $ast = $parser->parse('/[a-zA-Z0-9_]/u');
        $optimizer = new OptimizerNodeVisitor();

        /** @var RegexNode $newAst */
        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(CharClassNode::class, $newAst->pattern);
    }

    public function test_merges_nested_sequences(): void
    {
        // Sequence(Sequence(a, b), c) -> Sequence(a, b, c) -> Literal(abc)
        $inner = new SequenceNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('b', 0, 0)
        ], 0, 0);

        $outer = new SequenceNode([
            $inner,
            new LiteralNode('c', 0, 0)
        ], 0, 0);

        $optimizer = new OptimizerNodeVisitor();
        $result = $outer->accept($optimizer);

        $this->assertInstanceOf(LiteralNode::class, $result);
        $this->assertSame('abc', $result->value);
    }

    public function test_alternation_single_char_optimization(): void
    {
        // a|b|c -> [abc]
        $parser = new Parser();
        $ast = $parser->parse('/a|b|c/');
        $optimizer = new OptimizerNodeVisitor();

        /** @var RegexNode $newAst */
        $newAst = $ast->accept($optimizer);

        $this->assertInstanceOf(CharClassNode::class, $newAst->pattern);
    }

    public function test_alternation_optimization_skips_meta_chars(): void
    {
        // a|^|c -> Should NOT become [a^c] because ^ is meta in char class start
        // Ideally the optimizer is smart enough to know ^ is safe in middle,
        // but your logic strictly avoids meta chars.
        $parser = new Parser();
        $ast = $parser->parse('/a|^|c/'); // ^ is anchor here
        $optimizer = new OptimizerNodeVisitor();

        /** @var RegexNode $newAst */
        $newAst = $ast->accept($optimizer);

        // Should remain alternation because ^ was an AnchorNode in parse,
        // or if parsed as literals, the optimizer check prevents it.
        // Wait, parse('/a|^|c/') -> Alternation(Literal(a), Anchor(^), Literal(c)).
        // canAlternationBeCharClass checks for LiteralNode. AnchorNode returns false.
        // Correct behavior: No optimization.
        $this->assertNotInstanceOf(CharClassNode::class, $newAst->pattern);
    }
}
