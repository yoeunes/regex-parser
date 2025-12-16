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
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class ManualNodeInjectionTest extends TestCase
{
    public function test_sample_generator_fallbacks(): void
    {
        $generator = new SampleGeneratorNodeVisitor();

        // 1. CharTypeNode with unknown type
        // Parser only allows d, D, s, S, etc. We force '?' to hit the default match arm.
        $node = new CharTypeNode('?', 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 2. CharLiteralNode with invalid format
        // Parser ensures format. We force garbage to hit the fallback.
        $node = new CharLiteralNode('invalid', -1, CharLiteralType::UNICODE, 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 3. CharLiteralNode with invalid format
        $node = new CharLiteralNode('invalid', -1, CharLiteralType::OCTAL, 0, 0);
        $this->assertSame('?', $node->accept($generator));

        // 4. Alternation with no alternatives (Parser prevents this usually)
        $node = new AlternationNode([], 0, 0);
        $this->assertSame('', $node->accept($generator));
    }

    public function test_optimizer_alternation_logic(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // 1. Alternation containing non-literals (should NOT optimize to CharClass)
        // Case: (a|\d) -> Non-literal child
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0)
        ], 0, 0);

        $result = $node->accept($optimizer);
        $this->assertInstanceOf(AlternationNode::class, $result);

        // 2. Alternation containing multi-char literals (should NOT optimize)
        // Case: (a|abc) -> Literal length > 1
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('abc', 0, 0)
        ], 0, 0);
        $result = $node->accept($optimizer);
        $this->assertInstanceOf(AlternationNode::class, $result);

        // 3. Alternation containing meta-characters inside CharClass (should NOT optimize)
        // Case: (a|-) -> Hyphen is a meta-char in CharClass, unsafe to convert to [a-] without escaping logic
        $node = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('-', 0, 0)
        ], 0, 0);
        $result = $node->accept($optimizer);
        $this->assertInstanceOf(AlternationNode::class, $result);
    }
}
