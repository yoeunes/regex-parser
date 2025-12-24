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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\GroupNumberingCollector;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;

final class GroupNumberingCollectorTest extends TestCase
{
    public function test_collect_handles_branch_reset_define_and_class_operations(): void
    {
        $literalA = new LiteralNode('a', 0, 1);
        $literalB = new LiteralNode('b', 1, 2);

        $range = new RangeNode($literalA, $literalB, 0, 2);
        $charClass = new CharClassNode($range, false, 0, 2);
        $classOp = new ClassOperationNode(ClassOperationType::INTERSECTION, $charClass, $charClass, 0, 2);

        $capturedInDefine = new GroupNode($classOp, GroupType::T_GROUP_CAPTURING, null, null, 0, 2);
        $define = new DefineNode($capturedInDefine, 0, 2);

        $conditional = new ConditionalNode($literalA, $capturedInDefine, $literalB, 0, 2);
        $sequence = new SequenceNode([$define, $conditional], 0, 2);

        $namedGroup = new GroupNode($sequence, GroupType::T_GROUP_NAMED, 'foo', null, 0, 2);
        $capturing = new GroupNode($literalA, GroupType::T_GROUP_CAPTURING, null, null, 0, 1);

        $branchReset = new GroupNode(
            new AlternationNode([$capturing, $namedGroup], 0, 2),
            GroupType::T_GROUP_BRANCH_RESET,
            null,
            null,
            0,
            2,
        );

        $root = new RegexNode(new SequenceNode([$branchReset], 0, 2), '', '/', 0, 2);

        $collector = new GroupNumberingCollector();
        $result = $collector->collect($root);

        $this->assertGreaterThanOrEqual(1, $result->maxGroupNumber);
        $this->assertContains(1, $result->captureSequence);
        $this->assertArrayHasKey('foo', $result->namedGroups);
        $this->assertContains(1, $result->namedGroups['foo']);
    }
}
