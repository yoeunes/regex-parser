<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

class ParserUnsupportedSyntaxTest extends TestCase
{
    /**
     * Teste la syntaxe de backreference nommée (?P=name) qui n'est pas encore supportée.
     * Couvre le "if ($this->matchLiteral('=')) { throw ... }" dans parseGroupModifier.
     */
    public function test_unsupported_named_backref_syntax(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreferences (?P=name) are not supported yet');

        $parser->parse('/(?P=name)/');
    }

    /**
     * Teste une syntaxe invalide après (?P.
     * Couvre le throw final du bloc (?P...).
     */
    public function test_invalid_p_group_syntax(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid syntax after (?P');

        $parser->parse('/(?P!)/');
    }

    /**
     * Teste une conditionnelle avec un lookaround invalide (ex: (? (?~...) )
     * Couvre les "else { throw ... }" dans la détection des lookarounds conditionnels.
     */
    public function test_conditional_invalid_lookaround_char(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');

        // (? ( ? ~ ) ) -> ~ n'est pas = ou ! ou <
        $parser->parse('/(?(?~))/');
    }

    /**
     * Teste un conditionnel (?<...) qui n'est ni un lookbehind ni une named ref valide.
     */
    public function test_conditional_invalid_lookbehind_syntax(): void
    {
        $parser = new Parser();
        $this->expectException(ParserException::class);
        // Si on met (?< mais pas suivi de = ou !, c'est invalide dans ce contexte précis
        // sauf si c'est une named group, mais ici on teste le path du lookbehind
        $parser->parse('/(?(?<*))/');
    }
}
