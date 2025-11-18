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
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\Parser;

class DumperNodeVisitorTest extends TestCase
{
    public function testDumpSimple(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a(b|c)/');
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Group(type: capturing flags: )', $dump);
        $this->assertStringContainsString('Alternation', $dump);
    }
}
