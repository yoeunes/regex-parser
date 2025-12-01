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

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Parser;
use RegexParser\Regex;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

/**
 * Tests the private utility methods of the Parser class.
 */
class ParserUtilityTest extends TestCase
{
    private ParserAccessor $accessor;
    private Regex $regex;

    protected function setUp(): void
    {
        $parser = new Parser();
        $this->accessor = new ParserAccessor($parser);
        $this->regex = Regex::create();
    }

    public function test_extract_pattern_handles_escaped_delimiter_in_flags(): void
    {
        // Regex: /abc\/def/i
        // Le slash au milieu est échappé et ne doit pas être considéré comme le délimiteur de fin.
        // extractPatternAndFlags is now on Regex class
        [$pattern, $flags, $delimiter] = $this->regex->extractPatternAndFlags('/abc\/def/i');

        $this->assertSame('/', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc\/def', $pattern);
    }

    public function test_extract_pattern_handles_alternating_delimiters(): void
    {
        // Regex: (abc)i
        // extractPatternAndFlags is now on Regex class
        [$pattern, $flags, $delimiter] = $this->regex->extractPatternAndFlags('(abc)i');

        $this->assertSame('(', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc', $pattern);
    }

    public function test_extract_pattern_throws_on_malformed_flags(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "!"');

        // extractPatternAndFlags is now on Regex class
        $this->regex->extractPatternAndFlags('/abc/i!');
    }

    public function test_parse_group_name_throws_on_missing_name(): void
    {
        // Simuler un état où nous avons consommé '(?<' mais pas le nom
        $this->accessor->setTokens(['>', 'a', ')']); // Tentative de fermer immédiatement
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name at position');

        // La méthode attend que le délimiteur d'ouverture soit déjà consommé
        $this->accessor->callPrivateMethod('parseGroupName');
    }

    public function test_parse_group_name_with_quotes(): void
    {
        // Simuler: 'name' + '>'
        $tokens = [
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, "'", 1),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, 'test_name', 2),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, "'", 11),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, '>', 12),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        // parseGroupName handles the quotes itself
        $name = $this->accessor->callPrivateMethod('parseGroupName');
        $this->assertSame('test_name', $name);

        // Vérifie que l'accolade fermante (ou >) est toujours là pour la consommation
        $this->assertSame('>', $this->accessor->current()->value);
    }

    public function test_consume_literal_throws_on_type_mismatch(): void
    {
        // Token actuel est T_LITERAL with empty value, attend T_LITERAL with value 'a'
        $this->accessor->setTokens(['']);
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected error at position 0 (found literal with value )');

        $this->accessor->callPrivateMethod('consumeLiteral', ['a', 'Expected error']);
    }

    public function test_check_literal_returns_false_at_eof(): void
    {
        $this->accessor->setTokens(['']);
        $this->accessor->setPosition(0);

        $result = $this->accessor->callPrivateMethod('checkLiteral', ['a']);
        $this->assertFalse($result);
    }

    public function test_throws_on_quantifier_without_target(): void
    {
        // Le parser appelle parseQuantifiedAtom. Si le premier atom est absent,
        // et le token suivant est T_QUANTIFIER, il lève l'erreur.
        // Simuler: Token T_QUANTIFIER au début.
        $tokens = [
            $this->accessor->createToken(TokenType::T_QUANTIFIER, '*', 0),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier without target at position 0');

        $this->accessor->callPrivateMethod('parseQuantifiedAtom');
    }

    public function test_parse_atom_throws_on_unexpected_token(): void
    {
        // Simuler un jeton de fermeture de groupe inattendu dans un contexte atomique
        $tokens = [
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 0),
            $this->accessor->createToken(TokenType::T_EOF, '', 1),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token ")" (group_close) at position 0.');

        $this->accessor->callPrivateMethod('parseAtom');
    }

    public function test_parse_group_modifier_inline_flags_no_colon_valid(): void
    {
        // Regex: /?(im)/. Le parser consomme '('. Puis il consomme '?' (dans parseGroupModifier).
        // Il doit consommer les flags 'i', 'm', puis ')'
        $tokens = [
            $this->accessor->createToken(TokenType::T_LITERAL, 'i', 2),
            $this->accessor->createToken(TokenType::T_LITERAL, 'm', 3),
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 4),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0); // Position 0 -> Token 'i'

        // Le jeton '(?-' est géré par la logique du parser en amont.
        // Ici, on teste l'extraction des flags 'im' sans ':'.
        $node = $this->accessor->callPrivateMethod('parseGroupModifier');

        $this->assertInstanceOf(GroupNode::class, $node);
        $this->assertSame(GroupType::T_GROUP_INLINE_FLAGS, $node->type);
        $this->assertSame('im', $node->flags);
        $this->assertInstanceOf(LiteralNode::class, $node->child);
        $this->assertSame('', $node->child->value, 'Child should be an empty node.');
    }

    public function test_parse_group_modifier_throws_on_invalid_python_syntax(): void
    {
        // Simuler (?P[invalid]) - parseGroupModifier est appelé après avoir consommé (?
        // Position 0 = '/', 1 = '(', 2 = '?', 3 = 'P', 4 = '['
        $tokens = [
            $this->accessor->createToken(TokenType::T_LITERAL, 'P', 2), // P à la position 2
            $this->accessor->createToken(TokenType::T_LITERAL, '[', 3), // [ à la position 3
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 4),
            $this->accessor->createToken(TokenType::T_EOF, '', 5),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0); // Start at 'P'

        $this->expectException(ParserException::class);
        // Le message d'erreur est "Invalid syntax after (?P at position 2" (position de P)
        $this->expectExceptionMessage('Invalid syntax after (?P at position 2');

        $this->accessor->callPrivateMethod('parseGroupModifier');
    }

    public function test_parse_conditional_lookaround(): void
    {
        // Simuler (?(?=a))
        // The condition is a lookaround: (?=a)
        // Tokens: (?, =, a, ), )
        $tokens = [
            $this->accessor->createToken(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 2), // (?
            $this->accessor->createToken(TokenType::T_LITERAL, '=', 4), // =
            $this->accessor->createToken(TokenType::T_LITERAL, 'a', 5), // a
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 6), // Fermeture Lookahead
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 7), // Fermeture Conditionnelle
            $this->accessor->createToken(TokenType::T_EOF, '', 8),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0); // Start at position 0, on '(?'

        // parseConditionalCondition will parse the lookaround condition itself via parseAtom
        $condition = $this->accessor->callPrivateMethod('parseConditionalCondition');

        $this->assertInstanceOf(GroupNode::class, $condition);
        $this->assertSame(GroupType::T_GROUP_LOOKAHEAD_POSITIVE, $condition->type);
    }

    public function test_parse_conditional_assertion(): void
    {
        // Simuler (?(DEFINE)...)
        $tokens = [
            $this->accessor->createToken(TokenType::T_ASSERTION, 'DEFINE', 2),
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 8),
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 9), // Fermeture externe
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        // Simuler l'état où le parser a consommé '(?('
        $condition = $this->accessor->callPrivateMethod('parseConditionalCondition');

        $this->assertInstanceOf(AssertionNode::class, $condition);
        $this->assertSame('DEFINE', $condition->value);
    }

    public function test_parse_conditional_invalid_atom_throws(): void
    {
        // Simuler (?(.)...) où T_DOT n'est pas une condition valide (devrait être Backref ou Group)
        $tokens = [
            $this->accessor->createToken(TokenType::T_DOT, '.', 2), // Jeton T_DOT
            $this->accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 3),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional construct at position 2. Condition must be a group reference, lookaround, or (DEFINE).');

        $this->accessor->callPrivateMethod('parseConditionalCondition');
    }
}
