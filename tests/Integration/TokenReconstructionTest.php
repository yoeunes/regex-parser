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
use RegexParser\Token;
use RegexParser\TokenType;

final class TokenReconstructionTest extends TestCase
{
    /**
     * Ce test couvre 100% du "match" dans Parser::reconstructTokenValue.
     * Il itère sur chaque cas possible de l'Enum TokenType pour s'assurer qu'aucun n'est oublié.
     */
    public function test_reconstruct_every_token_type(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');

        // Mappage des tokens vers leur reconstruction attendue
        $map = [
            TokenType::T_LITERAL->value => 'a',
            TokenType::T_CHAR_TYPE->value => '\d',           // Ajoute \
            TokenType::T_GROUP_OPEN->value => '(',
            TokenType::T_GROUP_CLOSE->value => ')',
            TokenType::T_GROUP_MODIFIER_OPEN->value => '(?',
            TokenType::T_CHAR_CLASS_OPEN->value => '[',
            TokenType::T_CHAR_CLASS_CLOSE->value => ']',
            TokenType::T_QUANTIFIER->value => '*',
            TokenType::T_ALTERNATION->value => '|',
            TokenType::T_DOT->value => '.',
            TokenType::T_ANCHOR->value => '^',
            TokenType::T_EOF->value => '',
            TokenType::T_RANGE->value => '-',
            TokenType::T_NEGATION->value => '^',
            TokenType::T_BACKREF->value => '\1',             // Garde \
            TokenType::T_UNICODE->value => '\x00',           // Garde \
            TokenType::T_POSIX_CLASS->value => '[[:alnum:]]', // Reconstruit tout
            TokenType::T_ASSERTION->value => '\b',           // Ajoute \
            TokenType::T_UNICODE_PROP->value => '\p{L}',     // Cas standard
            TokenType::T_OCTAL->value => '\o{123}',          // Garde \
            TokenType::T_OCTAL_LEGACY->value => '\01',       // Ajoute \ (si code='01', devient '\01')
            TokenType::T_COMMENT_OPEN->value => '(?#',
            TokenType::T_PCRE_VERB->value => '(*FAIL)',
            TokenType::T_G_REFERENCE->value => '\g{1}',
            TokenType::T_KEEP->value => '\K',                // Ajoute \
            TokenType::T_LITERAL_ESCAPED->value => '\.',     // Ajoute \
            TokenType::T_QUOTE_MODE_START->value => '\Q',
            TokenType::T_QUOTE_MODE_END->value => '\E',
            TokenType::T_CALLOUT->value => '1',
        ];

        foreach (TokenType::cases() as $case) {
            // Valeur par défaut pour le token
            $val = match ($case) {
                TokenType::T_CHAR_TYPE => 'd',
                TokenType::T_ASSERTION => 'b',
                TokenType::T_KEEP => 'K',
                TokenType::T_OCTAL_LEGACY => '01',
                TokenType::T_LITERAL_ESCAPED => '.',
                TokenType::T_POSIX_CLASS => 'alnum',
                TokenType::T_PCRE_VERB => 'FAIL',
                TokenType::T_UNICODE_PROP => '{L}', // Pour déclencher le cas simple
                TokenType::T_CALLOUT => '1',
                default => $map[$case->value] ?? 'default'
            };

            // Cas spéciaux pour les méthodes qui transforment la valeur
            $expected = match ($case) {
                TokenType::T_CHAR_TYPE => '\d',
                TokenType::T_ASSERTION => '\b',
                TokenType::T_KEEP => '\K',
                TokenType::T_OCTAL_LEGACY => '\01',
                TokenType::T_LITERAL_ESCAPED => '\.',
                TokenType::T_POSIX_CLASS => '[[:alnum:]]',
                TokenType::T_PCRE_VERB => '(*FAIL)',
                TokenType::T_UNICODE_PROP => '\p{L}',
                TokenType::T_CALLOUT => '(?C1)',
                default => $val
            };

            $token = new Token($case, $val, 0);
            $result = $method->invoke($parser, $token);

            $this->assertSame($expected, $result, "Erreur de reconstruction pour {$case->name}");
        }
    }

    /**
     * Teste les branches spécifiques de T_UNICODE_PROP dans le parser.
     */
    public function test_reconstruct_unicode_prop_edge_cases(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');

        // Cas 1: Propriété courte sans accolades (ex: \pL) -> le parser stocke "L"
        $token = new Token(TokenType::T_UNICODE_PROP, 'L', 0);
        $this->assertSame('\pL', $method->invoke($parser, $token));

        // Cas 2: Propriété longue (ex: \p{Lu}) -> le parser stocke "Lu" (sans accolades ici pour tester la logique d'ajout)
        // Note: Votre parser semble stocker parfois avec, parfois sans.
        // Si reconstructTokenValue fait : (strlen($val) > 1) ? '\p{'.$val.'}'
        $token = new Token(TokenType::T_UNICODE_PROP, 'Lu', 0);
        $this->assertSame('\p{Lu}', $method->invoke($parser, $token));

        // Cas 3: Négation (ex: \p{^L}) -> le parser stocke "^L"
        $token = new Token(TokenType::T_UNICODE_PROP, '^L', 0);
        $this->assertSame('\p{^L}', $method->invoke($parser, $token));
    }
}
