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

namespace RegexParser\Tests\Lexer;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\TokenType;

final class LexerEdgeCasesTest extends TestCase
{
    public function test_escaped_special_chars(): void
    {
        // \t \n \r \f \e
        $tokens = new Lexer()->tokenize('\\t\\n\\r\\f\\e')->getTokens();

        // \t
        $this->assertSame("\t", $tokens[0]->value);
        // \n
        $this->assertSame("\n", $tokens[1]->value);
        // \r
        $this->assertSame("\r", $tokens[2]->value);
        // \f
        $this->assertSame("\f", $tokens[3]->value);
        // \e
        $this->assertSame("\e", $tokens[4]->value);
    }

    public function test_other_escaped_literal(): void
    {
        // \. should become .
        $tokens = new Lexer()->tokenize('\\.')->getTokens();

        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame('.', $tokens[0]->value);
    }
}
