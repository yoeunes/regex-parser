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

    public function test_dump_all_remaining_node_types(): void
    {
        // Test all remaining node types to achieve 100% coverage
        $parser = new Parser();
        $dumper = new DumperNodeVisitor();

        // Test visitDot
        $ast = $parser->parse('/./');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Dot(.)', $dump);

        // Test visitAnchor
        $ast = $parser->parse('/^test$/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Anchor(^)', $dump);
        $this->assertStringContainsString('Anchor($)', $dump);

        // Test visitAssertion
        $ast = $parser->parse('/\b\B\A\z\Z\G/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Assertion(\\b)', $dump);
        $this->assertStringContainsString('Assertion(\\B)', $dump);
        $this->assertStringContainsString('Assertion(\\A)', $dump);
        $this->assertStringContainsString('Assertion(\\z)', $dump);
        $this->assertStringContainsString('Assertion(\\Z)', $dump);
        $this->assertStringContainsString('Assertion(\\G)', $dump);

        // Test visitKeep
        $ast = $parser->parse('/\K/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Keep(\\K)', $dump);

        // Test visitCharType
        $ast = $parser->parse('/\d\s\w/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('CharType(\'\\d\')', $dump);
        $this->assertStringContainsString('CharType(\'\\s\')', $dump);
        $this->assertStringContainsString('CharType(\'\\w\')', $dump);

        // Test visitCharClass and visitRange
        $ast = $parser->parse('/[a-z0-9]/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('CharClass()', $dump);
        $this->assertStringContainsString('Range(', $dump);

        // Test visitBackref
        $ast = $parser->parse('/(a)\1/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Backref(\\1)', $dump);

        // Test visitUnicode
        $ast = $parser->parse('/\x41\u{1F600}/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Unicode(\\x41)', $dump);
        $this->assertStringContainsString('Unicode(\\u{1F600})', $dump);

        // Test visitUnicodeProp
        $ast = $parser->parse('/\p{L}\P{N}/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('UnicodeProp(\\p{L})', $dump);
        $this->assertStringContainsString('UnicodeProp(\\p{^N})', $dump);

        // Test visitOctalLegacy
        $ast = $parser->parse('/\01\07/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('OctalLegacy(\\01)', $dump);
        $this->assertStringContainsString('OctalLegacy(\\07)', $dump);

        // Test visitComment
        $ast = $parser->parse('/(?#this is a comment)/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Comment(\'this is a comment\')', $dump);

        // Test visitQuantifier
        $ast = $parser->parse('/a+b*c?d{3,5}/');
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Quantifier(quant: +, type: greedy)', $dump);
        $this->assertStringContainsString('Quantifier(quant: *, type: greedy)', $dump);
        $this->assertStringContainsString('Quantifier(quant: ?, type: greedy)', $dump);
        $this->assertStringContainsString('Quantifier(quant: {3,5}, type: greedy)', $dump);
    }
}
