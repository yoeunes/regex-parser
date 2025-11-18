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
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;

class ManualNodeInjectionTest extends TestCase
{
    public function test_sample_generator_fallbacks(): void
    {
        $generator = new SampleGeneratorVisitor();

        // 1. CharTypeNode with unknown type
        // Parser only allows d, D, s, S, etc. We force '?'
        $node = new CharTypeNode('?', 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 2. UnicodeNode with invalid format
        // Parser ensures format \xHH or \u{...}. We force garbage.
        $node = new UnicodeNode('invalid', 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 3. OctalNode with invalid format
        $node = new OctalNode('invalid', 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 4. Alternation with no alternatives (Parser prevents this)
        $node = new AlternationNode([], 0, 0);
        $this->assertSame('', $node->accept($generator));
    }

    public function test_optimizer_alternation_logic(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // 1. Alternation containing non-literals (should NOT optimize to CharClass)
        // (a|\d)
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0)
        ], 0, 0);

        $result = $node->accept($optimizer);
        // Should remain Alternation, not become CharClass
        $this->assertInstanceOf(AlternationNode::class, $result);

        // 2. Alternation containing multi-char literals
        // (a|abc)
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('abc', 0, 0)
        ], 0, 0);
        $result = $node->accept($optimizer);
        $this->assertInstanceOf(AlternationNode::class, $result);

        // 3. Alternation containing meta-characters inside CharClass
        // (a|-) - Hyphen is meta in CharClass
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('-', 0, 0)
        ], 0, 0);
        $result = $node->accept($optimizer);
        $this->assertInstanceOf(AlternationNode::class, $result);
    }
}
