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
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\Parser;

class SampleGeneratorVisitorTest extends TestCase
{
    private function assertSampleMatches(string $regex): void
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);

        // We generate multiple times to cover randomness
        $generator = new SampleGeneratorVisitor();

        for ($i = 0; $i < 10; ++$i) {
            $sample = $ast->accept($generator);
            $this->assertMatchesRegularExpression(
                $regex,
                $sample,
                "Generated sample '$sample' does not match regex '$regex'"
            );
        }
    }

    public function testGenerateSimple(): void
    {
        $this->assertSampleMatches('/abc/');
    }

    public function testGenerateAlternation(): void
    {
        $this->assertSampleMatches('/a|b|c/');
    }

    public function testGenerateQuantifiers(): void
    {
        $this->assertSampleMatches('/a{2,5}/');
        $this->assertSampleMatches('/b*/'); // Can generate empty string
        $this->assertSampleMatches('/c+/');
    }

    public function testGenerateCharClasses(): void
    {
        $this->assertSampleMatches('/[a-z]/');
        $this->assertSampleMatches('/[0-9]{3}/');
        $this->assertSampleMatches('/[a-zA-Z0-9]/');
    }

    public function testGenerateSpecialTypes(): void
    {
        $this->assertSampleMatches('/\d\s\w/');
    }

    public function testGenerateGroupsAndBackrefs(): void
    {
        // Complex test: backreference. The generator must remember what it generated.
        // (a|b)\1 must generate "aa" or "bb", but never "ab"
        $parser = new Parser();
        $ast = $parser->parse('/(a|b)\1/');
        $generator = new SampleGeneratorVisitor();

        $sample = $ast->accept($generator);
        $this->assertContains($sample, ['aa', 'bb']);
    }

    public function testSeeding(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/[a-z]{10}/');
        $generator = new SampleGeneratorVisitor();

        $generator->setSeed(12345);
        $sample1 = $ast->accept($generator);

        $generator->setSeed(12345);
        $sample2 = $ast->accept($generator);

        $this->assertSame($sample1, $sample2, 'Seeding should produce deterministic results');
    }
}
