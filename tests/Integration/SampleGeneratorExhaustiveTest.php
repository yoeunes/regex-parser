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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class SampleGeneratorExhaustiveTest extends TestCase
{
    private SampleGeneratorNodeVisitor $generator;

    protected function setUp(): void
    {
        $this->generator = new SampleGeneratorNodeVisitor();
    }

    public function test_generate_all_char_types(): void
    {
        // h, H, v, V, R, w, W, s, S, d, D
        $types = ['h', 'H', 'v', 'V', 'R', 'w', 'W', 's', 'S', 'd', 'D'];
        foreach ($types as $type) {
            $node = new CharTypeNode($type, 0, 0);
            $result = $node->accept($this->generator);
            $this->assertIsString($result);
        }
    }

    public function test_generate_all_posix_classes(): void
    {
        $classes = [
            'cntrl', 'graph', 'print', 'word',
            'blank', 'punct', 'xdigit', 'space'
        ];

        foreach ($classes as $class) {
            $node = new PosixClassNode($class, 0, 0);
            $result = $node->accept($this->generator);
            $this->assertIsString($result);
        }
    }
}
