<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\LexerException;
use RegexParser\Lexer;
use RegexParser\TokenType;

class LexerCommentTest extends TestCase
{
    /**
     * Teste un commentaire vide (?#)
     */
    public function test_lexer_empty_comment(): void
    {
        $lexer = new Lexer('/(?#)/');
        $tokens = $lexer->tokenize();

        // Doit contenir: /, (?#, ), /, EOF
        $this->assertCount(5, $tokens);
        $this->assertSame(TokenType::T_COMMENT_OPEN, $tokens[1]->type);
        $this->assertSame(TokenType::T_GROUP_CLOSE, $tokens[2]->type);
    }

    /**
     * Teste un commentaire non fermé à la fin de la chaîne.
     * Couvre: "if ($this->inCommentMode) { throw ... }" dans tokenize()
     * et la logique de fin de chaîne dans lexCommentMode().
     */
    public function test_lexer_unclosed_comment_at_eof(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unclosed comment ")" at end of input');

        $lexer = new Lexer('/(?#oups');
        $lexer->tokenize();
    }

    /**
     * Teste le contenu d'un commentaire.
     */
    public function test_lexer_comment_content(): void
    {
        $lexer = new Lexer('/(?# hello world )/');
        $tokens = $lexer->tokenize();

        // Token 0: /
        // Token 1: (?#
        // Token 2: hello world (T_LITERAL généré par lexCommentMode)
        // Token 3: )

        $this->assertSame(' hello world ', $tokens[2]->value);
    }
}
