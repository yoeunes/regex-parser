<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Benchmark;

use PhpBench\Attributes as Bench;
use RegexParser\Parser\Parser;

class ParserBenchmark
{
    #[Bench\Warmup(2)]
    #[Bench\Iterations(5)]
    #[Bench\Revs(100)]
    public function benchParseComplex(): void
    {
        $parser = new Parser();
        $parser->parse('/^(?P<name>[a-z]+)([0-9]{1,3})?$/i');
    }
}
