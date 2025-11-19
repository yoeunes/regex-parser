<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser;
use RegexParser\Node\CommentNode;

class ParserReconstructionTest extends TestCase
{
    /**
     * Ce test couvre la méthode Parser::reconstructTokenValue.
     * On injecte une "soupe" de tokens à l'intérieur d'un commentaire (?# ... ).
     * Le parser va tokeniser le contenu, puis appeler reconstructTokenValue pour recréer la chaîne du commentaire.
     */
    public function test_parser_reconstructs_all_token_types_in_comment(): void
    {
        $parser = new Parser();

        // Une regex contenant un commentaire avec TOUS les types de syntaxe possibles
        // Cela force le parser à passer dans chaque branch du match() de reconstructTokenValue
        $regex = '/(?#
            [abc]           # T_CHAR_CLASS_OPEN, T_CHAR_CLASS_CLOSE
            (group)         # T_GROUP_OPEN, T_GROUP_CLOSE
            (?:non)         # T_GROUP_MODIFIER_OPEN
            * + ?           # T_QUANTIFIER
            |               # T_ALTERNATION
            .               # T_DOT
            ^ $             # T_ANCHOR
            -               # T_RANGE (si contexte)
            \b \A           # T_ASSERTION
            \K              # T_KEEP
            \d \s           # T_CHAR_TYPE
            \g{1}           # T_G_REFERENCE
            \1 \k<name>     # T_BACKREF
            \01             # T_OCTAL_LEGACY
            \o{123}         # T_OCTAL
            \x00 \u{FFFF}   # T_UNICODE
            \p{L} \P{L}     # T_UNICODE_PROP
            \Q \E           # T_QUOTE_MODE_START/END
            \a              # T_LITERAL_ESCAPED
            text            # T_LITERAL
            [[:alnum:]]     # T_POSIX_CLASS (dans char class)
            (*FAIL)         # T_PCRE_VERB
        )/x'; // flag x pour ignorer les espaces dans la regex principale, mais pas dans le commentaire

        $ast = $parser->parse($regex);

        $this->assertInstanceOf(CommentNode::class, $ast->pattern);
        // On vérifie simplement que le contenu a été reconstruit (pas vide)
        $this->assertNotEmpty($ast->pattern->comment);
        // On vérifie la présence de quelques éléments clés reconstruits
        $this->assertStringContainsString('[abc]', $ast->pattern->comment);
        $this->assertStringContainsString('\p{L}', $ast->pattern->comment);
        $this->assertStringContainsString('(*FAIL)', $ast->pattern->comment);
    }
}
