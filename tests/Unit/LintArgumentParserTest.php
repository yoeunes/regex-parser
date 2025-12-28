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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\Command\LintArgumentParser;

final class LintArgumentParserTest extends TestCase
{
    public function test_argument_parser_class_instantiation(): void
    {
        $parser = new LintArgumentParser();
        $this->assertInstanceOf(LintArgumentParser::class, $parser);
    }
}
