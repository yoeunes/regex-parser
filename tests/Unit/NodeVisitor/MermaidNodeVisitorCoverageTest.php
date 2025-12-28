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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\MermaidNodeVisitor;

final class MermaidNodeVisitorCoverageTest extends TestCase
{
    public function test_limit_match_verb_is_rendered(): void
    {
        $visitor = new MermaidNodeVisitor();
        $regex = new RegexNode(new PcreVerbNode('LIMIT_MATCH=12', 0, 0), '', '/', 0, 0);

        $diagram = $regex->accept($visitor);

        $this->assertStringContainsString('LimitMatch: 12', $diagram);
    }

    public function test_callout_string_identifier_labels_are_rendered(): void
    {
        $visitor = new MermaidNodeVisitor();
        $regex = new RegexNode(new CalloutNode('named', true, 0, 0), '', '/', 0, 0);

        $diagram = $regex->accept($visitor);

        $this->assertStringContainsString('Callout: (?C&quot;named&quot;)', $diagram);
    }

    public function test_callout_default_identifier_labels_are_rendered(): void
    {
        $visitor = new MermaidNodeVisitor();
        $regex = new RegexNode(new CalloutNode('id', false, 0, 0), '', '/', 0, 0);

        $diagram = $regex->accept($visitor);

        $this->assertStringContainsString('Callout: (?Cid)', $diagram);
    }
}
