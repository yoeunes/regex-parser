<?php

namespace RegexParser\Tests\Integration;


use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\Tests\TestUtils\LexerAccessor;

class LexerInternalStateTest extends TestCase
{
    /**
     * Couvre la fin de lexCommentMode quand la parenthèse fermante manque.
     * Normalement tokenize() lance une exception après, mais nous voulons couvrir le "return null" interne.
     */
    public function test_lex_comment_mode_unterminated_returns_null(): void
    {
        $lexer = new Lexer('(?# ... sans fin');
        $accessor = new LexerAccessor($lexer);

        // On force le mode commentaire
        $accessor->callPrivateMethod('reset', ['(?# ... sans fin']);
        // On avance manuellement après le (?#
        $accessor->setPosition(3);

        // Appel direct à la méthode privée
        $result = $accessor->callPrivateMethod('lexCommentMode');

        // Elle doit consommer le reste et retourner null car pas de ')'
        $this->assertNull($result);
        // La position doit être à la fin
        $this->assertSame(13, $accessor->getPosition());
    }

    /**
     * Couvre la fin de lexQuoteMode quand \E manque.
     */
    public function test_lex_quote_mode_unterminated_returns_null(): void
    {
        $lexer = new Lexer('\Q ... sans fin');
        $accessor = new LexerAccessor($lexer);

        $accessor->callPrivateMethod('reset', ['\Q ... sans fin']);
        $accessor->setPosition(2); // Après \Q

        $result = $accessor->callPrivateMethod('lexQuoteMode');

        // Elle doit consommer le reste, retourner null (pas de token émis à la fin, juste changement d'état)
        $this->assertNull($result);
        $this->assertSame(13, $accessor->getPosition());
    }
}
