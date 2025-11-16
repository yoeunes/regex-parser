<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\DumperVisitor;

class DumperVisitorTest extends TestCase
{
    public function testDumpSimple(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a(b|c)/');
        $dumper = new DumperVisitor();
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Group(type: capturing flags: )', $dump);
        $this->assertStringContainsString('Alternation', $dump);
    }
}
