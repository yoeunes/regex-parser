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

class ParserReflectionTest extends TestCase
{
    /**
     * Ce test couvre 100% de la méthode privée Parser::reconstructTokenValue
     * qui contient un switch géant normalement inaccessible.
     */
    public function test_reconstruct_token_value_exhaustive(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');

        // Liste exhaustive de tous les cas du match()
        $scenarios = [
            [TokenType::T_LITERAL, 'a', 'a'],
            [TokenType::T_DOT, '.', '.'],
            [TokenType::T_CHAR_TYPE, 'd', '\d'], // Ajoute le backslash
            [TokenType::T_ASSERTION, 'b', '\b'],
            [TokenType::T_KEEP, 'K', '\K'],
            [TokenType::T_OCTAL_LEGACY, '01', '\01'],
            [TokenType::T_LITERAL_ESCAPED, '.', '\.'],
            [TokenType::T_BACKREF, '\1', '\1'],
            [TokenType::T_UNICODE, '\x41', '\x41'],
            [TokenType::T_UNICODE_PROP, 'L', '\pL'], // Short form
            [TokenType::T_UNICODE_PROP, '{Lu}', '\p{Lu}'], // Long form
            [TokenType::T_UNICODE_PROP, '^N', '\p{^N}'], // Negated
            [TokenType::T_POSIX_CLASS, 'alnum', '[[:alnum:]]'],
            [TokenType::T_PCRE_VERB, 'FAIL', '(*FAIL)'],
            [TokenType::T_GROUP_MODIFIER_OPEN, '(?', '(?'],
            [TokenType::T_COMMENT_OPEN, '(?#', '(?#'],
            [TokenType::T_QUOTE_MODE_START, '\Q', '\Q'],
            [TokenType::T_QUOTE_MODE_END, '\E', '\E'],
            [TokenType::T_EOF, '', ''],
        ];

        foreach ($scenarios as [$type, $value, $expected]) {
            $token = new Token($type, $value, 0);
            $result = $method->invoke($parser, $token);
            $this->assertSame($expected, $result, "Failed reconstruction for token type {$type->name}");
        }
    }

    /**
     * Teste exhaustivement la méthode privée reconstructTokenValue via la réflexion.
     * Cette méthode contient un grand switch/match qui est difficile à couvrir
     * entièrement via l'analyse normale des commentaires.
     */
    public function test_reconstruct_token_value_exhaustive_others(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');
        // $method->setAccessible(true); // Pas nécessaire en PHP moderne si invoke() est utilisé, mais bon à savoir

        // Scénarios : [TokenType, Valeur du token, Résultat attendu après reconstruction]
        $scenarios = [
            // Cas simples (retourne la valeur telle quelle)
            [TokenType::T_LITERAL, 'a', 'a'],
            [TokenType::T_NEGATION, '^', '^'],
            [TokenType::T_RANGE, '-', '-'],
            [TokenType::T_DOT, '.', '.'],
            [TokenType::T_GROUP_OPEN, '(', '('],
            [TokenType::T_GROUP_CLOSE, ')', ')'],
            [TokenType::T_CHAR_CLASS_OPEN, '[', '['],
            [TokenType::T_CHAR_CLASS_CLOSE, ']', ']'],
            [TokenType::T_QUANTIFIER, '+', '+'],
            [TokenType::T_ALTERNATION, '|', '|'],
            [TokenType::T_ANCHOR, '$', '$'],
            [TokenType::T_BACKREF, '\1', '\1'],
            [TokenType::T_G_REFERENCE, '\g{1}', '\g{1}'],
            [TokenType::T_UNICODE, '\x41', '\x41'],
            [TokenType::T_OCTAL, '\o{123}', '\o{123}'],

            // Cas avec ajout de backslash
            [TokenType::T_CHAR_TYPE, 'd', '\d'],
            [TokenType::T_ASSERTION, 'b', '\b'],
            [TokenType::T_KEEP, 'K', '\K'],
            [TokenType::T_OCTAL_LEGACY, '01', '\01'],
            [TokenType::T_LITERAL_ESCAPED, '.', '\.'],

            // Cas complexes
            // Unicode Prop: court, long, négation, accolades
            [TokenType::T_UNICODE_PROP, 'L', '\pL'],
            [TokenType::T_UNICODE_PROP, '{L}', '\p{L}'], // Déjà accolades
            [TokenType::T_UNICODE_PROP, 'Lu', '\p{Lu}'], // Long sans accolades -> ajoute {}
            [TokenType::T_UNICODE_PROP, '^L', '\p{^L}'], // Négation -> ajoute {}

            [TokenType::T_POSIX_CLASS, 'alnum', '[[:alnum:]]'],
            [TokenType::T_PCRE_VERB, 'FAIL', '(*FAIL)'],
            [TokenType::T_GROUP_MODIFIER_OPEN, '', '(?'], // Valeur ignorée
            [TokenType::T_COMMENT_OPEN, '', '(?#'],      // Valeur ignorée
            [TokenType::T_QUOTE_MODE_START, '', '\Q'],    // Valeur ignorée
            [TokenType::T_QUOTE_MODE_END, '', '\E'],      // Valeur ignorée
            [TokenType::T_EOF, '', ''],
        ];

        foreach ($scenarios as [$type, $value, $expected]) {
            $token = new Token($type, $value, 0);
            $result = $method->invoke($parser, $token);
            $this->assertSame($expected, $result, "Échec pour le token {$type->name} avec valeur '$value'");
        }
    }
}
