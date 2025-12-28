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
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;

final class GroupNumberingCollectorCoverageTest extends TestCase
{
    public function test_collect_branch_reset_handles_non_alternation_child(): void
    {
        $collector = new GroupNumberingCollector();
        $group = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_BRANCH_RESET, null, null, 0, 0);

        $method = (new \ReflectionClass($collector))->getMethod('collectBranchReset');
        $result = $method->invoke($collector, $group);

        $this->assertNull($result);
    }
}
