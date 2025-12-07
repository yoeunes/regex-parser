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

namespace RegexParser\Tests\Node;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\UnicodeNamedNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

final class UnicodeNamedNodeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, int, int}>
     */
    public static function data_provider_unicode_named(): \Iterator
    {
        yield 'latin_capital_a' => ['LATIN CAPITAL LETTER A', 0, 25];
        yield 'snowman' => ['SNOWMAN', 0, 12];
    }

    #[DataProvider('data_provider_unicode_named')]
    public function test_constructor_and_getters(string $name, int $start, int $end): void
    {
        $node = new UnicodeNamedNode($name, $start, $end);

        $this->assertSame($name, $node->name);
        $this->assertSame($start, $node->getStartPosition());
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_unicode_named(): void
    {
        $node = new UnicodeNamedNode('LATIN CAPITAL LETTER A', 0, 25);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitUnicodeNamed')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
