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
    public function test_dump_simple(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a(b|c)/');
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Group(type: capturing flags: )', $dump);
        $this->assertStringContainsString('Alternation', $dump);
    }

    public function test_dump_complex_alternation_and_sequence_indentation(): void
    {
        // /a|b/
        $parser = new Parser();
        $ast = $parser->parse('/a(b|c)d/');
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);

        // Expected to test Alternation and Sequence dumping/indentation
        $expected = <<<'EOT'
            Regex(delimiter: /, flags: )
              Sequence:
                Literal('a')
                Group(type: capturing flags: )
                  Alternation:
                    Literal('b')
                    Literal('c')
                Literal('d')
            EOT;
        $this->assertStringContainsString(trim($expected), trim($dump));
    }

    public function test_dump_complex_nodes(): void
    {
        // Test all node types that were likely missed: Conditional, Subroutine, Octal, Posix, PcreVerb
        $regex = '/(?P>name)(?(?=a)b)(*FAIL)\o{7}[[:alnum:]]/';
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);

        $this->assertStringContainsString('Subroutine(ref: name, syntax: \'P>\')', $dump);
        $this->assertStringContainsString('PcreVerb(value: FAIL)', $dump);
        $this->assertStringContainsString('Octal(\o{7})', $dump);
        $this->assertStringContainsString('PosixClass([[:alnum:]])', $dump);

        $this->assertStringContainsString('Conditional:', $dump);
        $this->assertStringContainsString('Condition: Group(type: lookahead_positive', $dump);
        $this->assertStringContainsString('Yes: Literal(\'b\')', $dump);
    }
}
