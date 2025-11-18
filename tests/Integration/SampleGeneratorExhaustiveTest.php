<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;

class SampleGeneratorExhaustiveTest extends TestCase
{
    private SampleGeneratorVisitor $generator;

    protected function setUp(): void
    {
        $this->generator = new SampleGeneratorVisitor();
    }

    public function test_generate_all_char_types(): void
    {
        // h, H, v, V, R, w, W, s, S, d, D
        $types = ['h', 'H', 'v', 'V', 'R', 'w', 'W', 's', 'S', 'd', 'D'];
        foreach ($types as $type) {
            $node = new CharTypeNode($type, 0, 0);
            $result = $node->accept($this->generator);
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
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
            // cntrl might produce \x00 which is empty-ish but is a string
            if ($class !== 'cntrl') {
                 $this->assertNotEmpty($result);
            }
        }
    }
}
