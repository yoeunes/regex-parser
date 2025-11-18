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
use RegexParser\Builder\RegexBuilder;

class RegexBuilderTest extends TestCase
{
    public function test_build_simple_sequence(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->startOfLine()
            ->literal('http')
            ->optional()
            ->literal('://')
            ->any()
            ->oneOrMore()
            ->endOfLine()
            ->compile();

        // The builder automatically escapes literals, so does / become \/ unless the delimiter changes?
        // Your current compiler doesn't escape '/' by default if it's not the delimiter.
        // Let's check the expected result.

        // ^http?://.+$
        // Note: your literals escape all meta chars. : and / are not meta.
        $this->assertSame('/^http?:\/\/.+$/', $regex);
    }

    public function test_build_alternation(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->literal('cat')
            ->or
            ->literal('dog')
            ->compile();

        $this->assertSame('/cat|dog/', $regex);
    }

    public function test_build_char_class(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->charClass(function ($c): void {
                $c->range('a', 'z')
                    ->digit();
            })
            ->oneOrMore()
            ->compile();

        $this->assertSame('/[a-z\d]+/', $regex);
    }

    public function test_build_named_group(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->namedGroup('id', function ($b): void {
                $b->digit()->oneOrMore();
            })
            ->withFlags('i')
            ->compile();

        $this->assertSame('/(?<id>\d+)/i', $regex);
    }

    public function test_safe_escaping_in_literal(): void
    {
        $builder = new RegexBuilder();
        // literal() must escape special characters
        $regex = $builder->literal('a.b*c')->compile();

        $this->assertSame('/a\.b\*c/', $regex);
    }
}
