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

namespace RegexParser\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Internal\PatternParser;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Parser;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

/**
 * Tests the private utility methods of the Parser class.
 */
final class ParserUtilityTest extends TestCase
{
    private ParserAccessor $accessor;

    protected function setUp(): void
    {
        $parser = new Parser();
        $this->accessor = new ParserAccessor($parser);
    }

    public function test_extract_pattern_handles_escaped_delimiter_in_flags(): void
    {
        // Regex: /abc\/def/i
        // The slash in the middle is escaped and must not be considered as the ending delimiter.
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/abc\/def/i');

        $this->assertSame('/', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc\/def', $pattern);
    }

    public function test_extract_pattern_handles_alternating_delimiters(): void
    {
        // Regex: (abc)i
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('(abc)i');

        $this->assertSame('(', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc', $pattern);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideWhitespaceSeparatedFlags(): array
    {
        return [
            'space separated single flag' => ['/a/ i', 'i'],
            'newline before flag' => ["/a/\n i", 'i'],
            'trailing whitespace after flag' => ['/a/i ', 'i'],
            'space separated multiple flags' => ['/a/ i u', 'iu'],
        ];
    }

    /**
     * @dataProvider provideWhitespaceSeparatedFlags
     */
    public function test_extract_pattern_accepts_whitespace_in_flags(string $regex, string $expectedFlags): void
    {
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);

        $this->assertSame('/', $delimiter);
        $this->assertSame('a', $pattern);
        $this->assertSame($expectedFlags, $flags);
    }

    public function test_extract_pattern_throws_on_malformed_flags(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "!"');

        PatternParser::extractPatternAndFlags('/abc/i!');
    }

    public function test_extract_pattern_rejects_unknown_r_modifier(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "r"');

        PatternParser::extractPatternAndFlags('/a/r');
    }

    public function test_extract_pattern_handles_leading_whitespace_with_paired_delimiter(): void
    {
        // Edge case: Leading whitespace + paired delimiter
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('  {foo}i');

        $this->assertSame('{', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('foo', $pattern);
    }

    public function test_extract_pattern_handles_escaped_delimiter_near_end(): void
    {
        // Edge case: Escaped delimiter near the end: "/a\/b/i" should parse pattern "a\/b"
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/a\/b/i');

        $this->assertSame('/', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('a\\/b', $pattern);
    }

    public function test_extract_pattern_simple(): void
    {
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/foo/');

        $this->assertSame('/', $delimiter);
        $this->assertSame('', $flags);
        $this->assertSame('foo', $pattern);
    }

    public function test_extract_pattern_handles_lots_of_backslashes_before_delimiter(): void
    {
        // Edge case: Lots of backslashes before delimiter: "/foo\\\\//" (even number, not escaped)
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/foo\\\\//');

        $this->assertSame('/', $delimiter);
        $this->assertSame('', $flags);
        $this->assertSame('foo\\\\/', $pattern);
    }

    public function test_extract_pattern_handles_very_long_patterns(): void
    {
        // Edge case: Very long patterns near max_pattern_length
        $longPattern = str_repeat('a', 1000);
        $regex = '/'.$longPattern.'/i';
        [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags($regex);

        $this->assertSame('/', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame($longPattern, $pattern);
    }

    public function test_parse_group_name_throws_on_missing_name(): void
    {
        // Simulate a state where we have consumed '(?<' but not the name
        $this->accessor->setTokens(['>', 'a', ')']); // Attempt to close immediately
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name at position');

        // The method expects that the opening delimiter has already been consumed
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

        // Verifies that the closing brace (or >) is still there for consumption
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
        // and the next token is T_QUANTIFIER, it raises the error.
        // Simulate: Token T_QUANTIFIER at the beginning.
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

        // The token '(?-' is handled by the upstream parser logic.
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
        // Simulate (?P[invalid]) - parseGroupModifier is called after consuming (?
        // Position 0 = '/', 1 = '(', 2 = '?', 3 = 'P', 4 = '['
        $tokens = [
            $this->accessor->createToken(TokenType::T_LITERAL, 'P', 2), // P at position 2
            $this->accessor->createToken(TokenType::T_LITERAL, '[', 3), // [ at position 3
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

        // Simulate the state where the parser has consumed '(?('
        $condition = $this->accessor->callPrivateMethod('parseConditionalCondition');

        $this->assertInstanceOf(AssertionNode::class, $condition);
        $this->assertSame('DEFINE', $condition->value);
    }

    public function test_parse_conditional_invalid_atom_throws(): void
    {
        // Simulate (?(.)...) where T_DOT is not a valid condition (should be Backref or Group)
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
