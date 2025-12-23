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

namespace RegexParser\Tests\Unit\Node;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

final class UnicodeNodeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, int, int}>
     */
    public static function data_provider_unicode(): \Iterator
    {
        yield 'hex_byte' => ['\\x41', 0, 4];
        yield 'unicode_code_point' => ['\\u{20AC}', 0, 8];
        yield 'extended_unicode' => ['\\u{1F600}', 0, 9];
    }

    #[DataProvider('data_provider_unicode')]
    public function test_constructor_and_getters(string $code, int $start, int $end): void
    {
        $node = new UnicodeNode($code, $start, $end);

        $this->assertSame($code, $node->code);
        $this->assertSame($start, $node->getStartPosition());
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_unicode(): void
    {
        $node = new UnicodeNode('\\u{A}', 0, 5);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitUnicode')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
