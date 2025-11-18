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

namespace RegexParser\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RegexParser\Builder\CharClassBuilder;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\RangeNode;

class CharClassBuilderTest extends TestCase
{
    public function test_literal_adds_single_chars(): void
    {
        $builder = new CharClassBuilder();
        $parts = $builder->literal('abc')->build();

        $this->assertCount(3, $parts);
        $this->assertInstanceOf(LiteralNode::class, $parts[0]);
        $this->assertSame('a', $parts[0]->value);
    }

    public function test_range_adds_range_node(): void
    {
        $builder = new CharClassBuilder();
        $parts = $builder->range('a', 'z')->build();

        $this->assertCount(1, $parts);
        $this->assertInstanceOf(RangeNode::class, $parts[0]);
        $this->assertSame('a', $parts[0]->start->value);
        $this->assertSame('z', $parts[0]->end->value);
    }

    public function test_range_throws_on_multi_char_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Range parts must be single characters.');

        $builder = new CharClassBuilder();
        $builder->range('aa', 'z');
    }

    public function test_char_type_methods_add_correct_nodes(): void
    {
        $builder = new CharClassBuilder();
        $parts = $builder
            ->digit()
            ->notDigit()
            ->whitespace()
            ->notWhitespace()
            ->word()
            ->notWord()
            ->build();

        $this->assertCount(6, $parts);
        $this->assertInstanceOf(CharTypeNode::class, $parts[0]);
        $this->assertSame('d', $parts[0]->value);
        $this->assertSame('D', $parts[1]->value);
        $this->assertSame('s', $parts[2]->value);
        $this->assertSame('S', $parts[3]->value);
        $this->assertSame('w', $parts[4]->value);
        $this->assertSame('W', $parts[5]->value);
    }

    public function test_posix_adds_posix_class_node(): void
    {
        $builder = new CharClassBuilder();
        $parts = $builder->posix('alnum')->build();

        $this->assertCount(1, $parts);
        $this->assertInstanceOf(PosixClassNode::class, $parts[0]);
        $this->assertSame('alnum', $parts[0]->class);
    }
}
