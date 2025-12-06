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
use RegexParser\Node\OctalNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

final class OctalNodeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, int, int}>
     */
    public static function data_provider_octal(): \Iterator
    {
        yield 'single_digit' => ['\\o{7}', 0, 5];
        yield 'three_digits' => ['\\o{777}', 0, 7];
        yield 'zero' => ['\\o{0}', 0, 5];
    }

    #[DataProvider('data_provider_octal')]
    public function test_constructor_and_getters(string $code, int $start, int $end): void
    {
        $node = new OctalNode($code, $start, $end);

        $this->assertSame($code, $node->code);
        $this->assertSame($start, $node->getStartPosition());
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_octal(): void
    {
        $node = new OctalNode('\\o{77}', 0, 6);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitOctal')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
