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
use RegexParser\Node\CommentNode;
use RegexParser\Parser;

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
        // Note: In PCRE (?#...) comments, the comment ends at the first ) character
        $regex = '/(?#[abc] (group (?:non * + ? | . ^ $ - \b \A \K \d \s \g{1} \1 \k<name> \01 \o{123} \x00 \u{FFFF} \p{L} \P{L} \Q \E \a text [[:alnum:]] (*FAIL)/x';

        $ast = $parser->parse($regex);

        $this->assertInstanceOf(CommentNode::class, $ast->pattern);
        // On vérifie simplement que le contenu a été reconstruit (pas vide)
        $this->assertNotEmpty($ast->pattern->comment);
        // On vérifie la présence de quelques éléments clés reconstruits
        $this->assertStringContainsString('[abc]', $ast->pattern->comment);
        $this->assertStringContainsString('\p{L}', $ast->pattern->comment);
        $this->assertStringContainsString('(*FAIL', $ast->pattern->comment);
    }
}
