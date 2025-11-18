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
use RegexParser\Parser;

class LexerStateTest extends TestCase
{
    public function test_parser_reuses_lexer_and_resets(): void
    {
        $this->expectNotToPerformAssertions();

        $parser = new Parser();

        // First parse creates Lexer
        $parser->parse('/a/');

        // Second parse reuses Lexer and calls reset()
        $parser->parse('/b/');
    }
}
