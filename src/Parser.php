<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser;

use RegexParser\Exception\ParserException;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * The Parser.
 * It consumes a stream of Tokens from the Lexer and builds an
 * Abstract Syntax Tree (AST) based on a formal grammar.
 * It is now responsible for handling delimiters.
 */
class Parser
{
    /**
     * Default hard limit on the regex string length to prevent excessive processing.
     */
    public const DEFAULT_MAX_PATTERN_LENGTH = 100000;

    /** @var array<Token> */
    private array $tokens;
    private int $position = 0;
    private string $delimiter;
    private string $flags;
    private int $patternLength = 0;

    private int $maxPatternLength;

    /**
     * @param array<string, mixed> $options Configuration options
     *                                      * 'max_pattern_length': (int) Max length of the regex string to parse.
     *                                      Defaults to 100000.
     */
    public function __construct(array $options = [])
    {
        $options = array_merge([
            'max_pattern_length' => self::DEFAULT_MAX_PATTERN_LENGTH,
        ], $options);

        $this->maxPatternLength = (int) $options['max_pattern_length'];
    }

    /**
     * Parses the full regex string, including delimiters and flags.
     *
     * @return RegexNode the root node of the AST, containing the pattern and flags
     *
     * @throws ParserException if a syntax error is found
     */
    public function parse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw new ParserException(\sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength));
        }

        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $this->delimiter = $delimiter;
        $this->flags = $flags;
        $this->patternLength = mb_strlen($pattern);

        $lexer = new Lexer($pattern);
        $this->tokens = $lexer->tokenize();
        $this->position = 0;

        $patternNode = $this->parseAlternation();
        $this->consume(TokenType::T_EOF, 'Unexpected content at end of pattern');

        // The RegexNode spans the entire pattern
        return new RegexNode($patternNode, $this->flags, $this->delimiter, 0, $this->patternLength);
    }

    /**
     * Extracts the pattern, flags, and delimiter from the full regex string.
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     *
     * @throws ParserException
     */
    private function extractPatternAndFlags(string $regex): array
    {
        if (\strlen($regex) < 2) {
            throw new ParserException('Regex is too short.');
        }

        $delimiter = $regex[0];
        $delimiterMap = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $closingDelimiter = $delimiterMap[$delimiter] ?? $delimiter;

        $lastPos = strrpos($regex, $closingDelimiter);

        // Check for escaped delimiter
        if (false !== $lastPos && $lastPos > 0) {
            $escaped = false;
            for ($i = $lastPos - 1; $i >= 0 && '\\' === $regex[$i]; --$i) {
                $escaped = !$escaped;
            }
            if ($escaped) {
                $lastPos = strrpos(substr($regex, 0, $lastPos), $closingDelimiter);
            }
        }

        if (false === $lastPos || 0 === $lastPos) {
            throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
        }

        $pattern = substr($regex, 1, $lastPos - 1);
        $flags = substr($regex, $lastPos + 1);

        $unknownFlags = preg_replace('/[imsxADSUXJu]/', '', $flags);
        if ('' !== $unknownFlags) {
            throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $unknownFlags));
        }

        return [$pattern, $flags, $delimiter];
    }

    // --- GRAMMAR ---
    // alternation      → sequence ( "|" sequence )*
    // sequence         → quantifiedAtom*
    // quantifiedAtom   → atom ( QUANTIFIER )?
    // atom             → T_LITERAL | T_CHAR_TYPE | T_DOT | T_ANCHOR | T_PCRE_VERB | T_KEEP | group | char_class
    // group            → T_GROUP_OPEN alternation T_GROUP_CLOSE
    //                  | T_GROUP_MODIFIER_OPEN group_modifier T_GROUP_CLOSE
    // ... (etc)

    /**
     * Parses an alternation (e.g., "a|b").
     */
    private function parseAlternation(): NodeInterface
    {
        $nodes = [$this->parseSequence()];

        while ($this->match(TokenType::T_ALTERNATION)) {
            $nodes[] = $this->parseSequence();
        }

        if (1 === \count($nodes)) {
            return $nodes[0];
        }

        $startPos = $nodes[0]->getStartPosition();
        $endPos = end($nodes)->getEndPosition();

        return new AlternationNode($nodes, $startPos, $endPos);
    }

    /**
     * Parses a sequence of atoms (e.g., "abc").
     */
    private function parseSequence(): NodeInterface
    {
        $nodes = [];
        $startPos = $this->current()->position;

        // Continue parsing as long as it's not a sequence terminator
        while (!$this->check(TokenType::T_GROUP_CLOSE)
            && !$this->check(TokenType::T_ALTERNATION)
            && !$this->check(TokenType::T_EOF)
        ) {
            $nodes[] = $this->parseQuantifiedAtom();
        }

        if (empty($nodes)) {
            // "Empty" node at the current position
            return new LiteralNode('', $startPos, $startPos);
        }

        if (1 === \count($nodes)) {
            return $nodes[0];
        }

        $endPos = end($nodes)->getEndPosition();

        return new SequenceNode($nodes, $startPos, $endPos);
    }

    /**
     * Parses an atom that may or may not be quantified (e.g., "a", "a*?").
     */
    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $token = $this->previous();
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException('Quantifier without target at position '.$token->position);
            }

            // Assertions, anchors, and verbs cannot be quantified.
            if ($node instanceof AnchorNode || $node instanceof AssertionNode || $node instanceof PcreVerbNode || $node instanceof KeepNode) {
                // This block is now type-safe and fixes the PHPStan error.
                $nodeName = '';
                if ($node instanceof AnchorNode) {
                    $nodeName = $node->value;
                } elseif ($node instanceof AssertionNode) {
                    $nodeName = '\\'.$node->value;
                } elseif ($node instanceof PcreVerbNode) {
                    $nodeName = '(*'.$node->verb.')';
                } elseif ($node instanceof KeepNode) {
                    $nodeName = '\K';
                }

                throw new ParserException(\sprintf('Quantifier "%s" cannot be applied to assertion or verb "%s" at position %d', $token->value, $nodeName, $token->position));
            }

            [$quantifier, $type] = $this->parseQuantifierValue($token->value);

            $startPos = $node->getStartPosition(); // Start of the node being quantified
            $endPos = $token->position + mb_strlen($token->value); // End of the quantifier token

            $node = new QuantifierNode($node, $quantifier, $type, $startPos, $endPos);
        }

        return $node;
    }

    /**
     * Helper to split quantifier value (e.g. "*?") into value ("*") and type (LAZY).
     *
     * @return array{0: string, 1: QuantifierType}
     */
    private function parseQuantifierValue(string $value): array
    {
        $lastChar = substr($value, -1);
        $baseValue = substr($value, 0, -1);

        // e.g. *? or +? or ??
        // Note: $value can be '?' so strlen > 1 is crucial
        if ('?' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_LAZY];
        }

        // e.g. *+ or ++
        // Note: $value can be '+' so strlen > 1 is crucial
        if ('+' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_POSSESSIVE];
        }

        // It's a normal, greedy quantifier (e.g. *, +, or ?)
        return [$value, QuantifierType::T_GREEDY];
    }

    /**
     * Parses a single "atom" (the smallest unit).
     */
    private function parseAtom(): NodeInterface
    {
        $token = $this->current(); // Peek at the current token for its position
        $startPos = $token->position;

        if ($this->match(TokenType::T_LITERAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new LiteralNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // +1 for the \

            return new CharTypeNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_DOT)) {
            $endPos = $startPos + 1;

            return new DotNode($startPos, $endPos);
        }

        if ($this->match(TokenType::T_ANCHOR)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new AnchorNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_ASSERTION)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // +1 for the \

            return new AssertionNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_BACKREF)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new BackrefNode($token->value, $startPos, $endPos);
        }

        // Handle \g references which can be Backrefs or Subroutines
        if ($this->match(TokenType::T_G_REFERENCE)) {
            $token = $this->previous();
            $value = $token->value;
            $endPos = $startPos + mb_strlen($value);

            // \g{N} or \gN (numeric, incl. relative) -> Backreference
            if (preg_match('/^\\\\g\{?([0-9+-]+)\}?$/', $value, $m)) {
                return new BackrefNode($value, $startPos, $endPos);
            }

            // \g<name> or \g{name} (non-numeric) -> Subroutine
            if (preg_match('/^\\\\g<(\w+)>$/', $value, $m) || preg_match('/^\\\\g\{(\w+)\}$/', $value, $m)) {
                // Pass just the name/ref, and the syntax type 'g'
                return new SubroutineNode($m[1], 'g', $startPos, $endPos);
            }

            throw new ParserException('Invalid \g reference syntax: '.$value.' at position '.$token->position);
        }

        if ($this->match(TokenType::T_UNICODE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new UnicodeNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_OCTAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new OctalNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_OCTAL_LEGACY)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // +1 for the \

            return new OctalLegacyNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            // Calculate end pos based on original syntax (\p{L} vs \pL)
            $len = 2 + mb_strlen($token->value); // \p or \P + value
            if (mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) {
                $len += 2; // for {}
            }
            $endPos = $startPos + $len;

            return new UnicodePropNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_KEEP)) {
            $endPos = $startPos + 2; // \K

            return new KeepNode($startPos, $endPos);
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $startToken = $this->previous();
            $expr = $this->parseAlternation(); // Recurse
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_CAPTURING, null, null, $startToken->position, $endPos);
        }

        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            // parseGroupModifier handles its own positions
            return $this->parseGroupModifier();
        }

        if ($this->match(TokenType::T_COMMENT_OPEN)) {
            // parseComment handles its own positions
            return $this->parseComment();
        }

        if ($this->match(TokenType::T_CHAR_CLASS_OPEN)) {
            // parseCharClass handles its own positions
            return $this->parseCharClass();
        }

        // Handle PCRE Verbs
        if ($this->match(TokenType::T_PCRE_VERB)) {
            $token = $this->previous();
            // (*VERB)
            $endPos = $startPos + mb_strlen($token->value) + 3; // +3 for "(*)"

            return new PcreVerbNode($token->value, $startPos, $endPos);
        }

        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        $val = $this->current()->value;
        $type = $this->current()->type->value;
        throw new ParserException(\sprintf('Unexpected token "%s" (%s) at %s.', $val, $type, $at));
    }

    /**
     * Parses a comment group (?#comment).
     *
     * @throws ParserException
     */
    private function parseComment(): CommentNode
    {
        $startToken = $this->previous(); // (?#
        $startPos = $startToken->position;

        $comment = $this->consumeWhile(fn (string $c) => ')' !== $c);
        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close comment');
        $endPos = $endToken->position + 1;

        return new CommentNode($comment, $startPos, $endPos);
    }

    /**
     * Parses a special group that starts with "(?".
     * This is the new, robust logic.
     *
     * @throws ParserException
     */
    private function parseGroupModifier(): NodeInterface
    {
        $startToken = $this->previous(); // (?
        $startPos = $startToken->position;

        // 1. Check for Python-style 'P' groups
        if ($this->matchLiteral('P')) {
            if ($this->matchLiteral('<')) { // (?P<name>...)
                $name = $this->parseGroupName();
                $this->consumeLiteral('>', 'Expected > after group name');
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
                $endPos = $endToken->position + 1;

                return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name, null, $startPos, $endPos);
            }
            if ($this->matchLiteral('>')) { // (?P>name) subroutine
                $name = $this->parseSubroutineName();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');
                $endPos = $endToken->position + 1;

                return new SubroutineNode($name, 'P>', $startPos, $endPos);
            }
            if ($this->matchLiteral('=')) { // (?P=name) backref
                throw new ParserException('Backreferences (?P=name) are not supported yet.');
            }
            throw new ParserException('Invalid syntax after (?P at position '.$startPos);
        }

        // 2. Check for standard lookarounds and named groups
        if ($this->matchLiteral('<')) {
            // (?<...> : lookbehind or named group
            if ($this->matchLiteral('=')) { // (?<=...)
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
                $endPos = $endToken->position + 1;

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_POSITIVE, null, null, $startPos, $endPos);
            }
            if ($this->matchLiteral('!')) { // (?<!...)
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
                $endPos = $endToken->position + 1;

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE, null, null, $startPos, $endPos);
            }
            // (?<name>...)
            $name = $this->parseGroupName();
            $this->consumeLiteral('>', 'Expected > after group name');
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name, null, $startPos, $endPos);
        }

        // 3. Check for conditional (?(...)
        if ($this->match(TokenType::T_GROUP_OPEN)) {
            // Conditional (?(condition)yes|no)
            $condition = $this->parseConditionalCondition();
            $yes = $this->parseAlternation();
            if ($this->match(TokenType::T_ALTERNATION)) {
                $no = $this->parseAlternation();
            } else {
                $currentPos = $this->current()->position;
                $no = new LiteralNode('', $currentPos, $currentPos);
            }
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new ConditionalNode($condition, $yes, $no, $startPos, $endPos);
        }

        // 4. Check for Subroutines
        if ($this->matchLiteral('&')) { // (?&name)
            $name = $this->parseSubroutineName();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');
            $endPos = $endToken->position + 1;

            return new SubroutineNode($name, '&', $startPos, $endPos);
        }

        if ($this->matchLiteral('R')) { // (?R)
            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
                $endPos = $endToken->position + 1;

                return new SubroutineNode('R', '', $startPos, $endPos);
            }
            --$this->position;
            throw new ParserException('Invalid syntax after (?R at position '.$startPos);
        }

        // Check for (?1), (?-1), (?0)
        $num = '';
        if ($this->matchLiteral('-')) {
            $num = '-';
        }
        if ($this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value)) {
            $num .= $this->current()->value;
            $this->advance(); // Consume first digit
            $num .= $this->consumeWhile(fn (string $c) => ctype_digit($c)); // Consume all digits

            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');
            $endPos = $endToken->position + 1;

            return new SubroutineNode($num, '', $startPos, $endPos);
        }
        if ('-' === $num) {
            --$this->position; // Rewind '-' if it wasn't followed by a digit
        }

        // 5. Check for simple non-capturing, lookaheads, atomic
        if ($this->matchLiteral(':')) { // (?:...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_NON_CAPTURING, null, null, $startPos, $endPos);
        }
        if ($this->matchLiteral('=')) { // (?=...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, $startPos, $endPos);
        }
        if ($this->matchLiteral('!')) { // (?!...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE, null, null, $startPos, $endPos);
        }
        if ($this->matchLiteral('>')) { // (? >...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_ATOMIC, null, null, $startPos, $endPos);
        }

        // 6. *Last*, check for inline flags.
        $flags = $this->consumeWhile(fn (string $c) => (bool) preg_match('/^[imsxADSUXJ-]+$/', $c));
        if ('' !== $flags) {
            $this->consumeLiteral(':', 'Expected : after inline flags');
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_INLINE_FLAGS, null, $flags, $startPos, $endPos);
        }

        throw new ParserException('Invalid group modifier syntax at position '.$startPos);
    }

    /**
     * Parses the condition in a conditional group (?(condition)...).
     */
    private function parseConditionalCondition(): NodeInterface
    {
        $startPos = $this->current()->position;

        if ($this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value)) {
            // Numeric (?(1)...)
            $this->advance(); // Consume the first digit
            $num = (string) ($this->previous()->value.$this->consumeWhile(fn (string $c) => ctype_digit($c)));
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) after condition number');
            $endPos = $endToken->position; // Position of the ')'

            return new BackrefNode($num, $startPos, $endPos);
        }

        if ($this->matchLiteral('<') || $this->matchLiteral('{')) {
            // Named (?(<name>)...) or (?({name})...)
            $open = $this->previous()->value;
            $name = $this->parseGroupName();
            $close = '<' === $open ? '>' : '}';
            $this->consumeLiteral($close, "Expected $close after condition name");
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) after named condition');
            $endPos = $endToken->position; // Position of the ')'

            return new BackrefNode($name, $startPos, $endPos);
        }
        if ($this->matchLiteral('R')) {
            // Recursion (?(R)...)
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) after R condition');
            $endPos = $endToken->position; // Position of the ')'

            return new LiteralNode('R', $startPos, $endPos); // Special recursion condition
        }

        // Lookaround or assertion as condition (?(?=...)...)
        // The condition *is* the atom, which includes its own end parenthesis
        return $this->parseAtom();
    }

    /**
     * Parses the name of a named group, handling quotes.
     *
     * @throws ParserException
     */
    private function parseGroupName(): string
    {
        $token = $this->current();

        // Handle 'name' or "name"
        if (TokenType::T_LITERAL === $token->type && ("'" === $token->value || '"' === $token->value)) {
            $quote = $token->value;
            $this->advance(); // Consume opening quote
            $nameToken = $this->consume(TokenType::T_LITERAL, 'Expected group name');
            if ($this->current()->value !== $quote) {
                throw new ParserException('Expected closing quote '.$quote.' at position '.$this->current()->position);
            }
            $this->advance(); // Consume closing quote

            return $nameToken->value;
        }

        // Handle <name>
        $name = '';
        while (!$this->checkLiteral('>') && !$this->isAtEnd()) {
            if ($this->check(TokenType::T_LITERAL)) {
                $name .= $this->current()->value;
                $this->advance();
            } else {
                throw new ParserException('Unexpected token in group name: '.$this->current()->value);
            }
        }

        if ('' === $name) {
            throw new ParserException('Expected group name at position '.$this->current()->position);
        }

        return $name;
    }

    /**
     * Parses a character class (e.g., "[a-z\d]").
     *
     * @throws ParserException
     */
    private function parseCharClass(): CharClassNode
    {
        $startToken = $this->previous(); // [
        $startPos = $startToken->position;
        $isNegated = $this->match(TokenType::T_NEGATION);
        $parts = [];

        while (!$this->check(TokenType::T_CHAR_CLASS_CLOSE) && !$this->isAtEnd()) {
            $parts[] = $this->parseCharClassPart();
        }

        $endToken = $this->consume(TokenType::T_CHAR_CLASS_CLOSE, 'Expected "]" to close character class');
        $endPos = $endToken->position + 1;

        return new CharClassNode($parts, $isNegated, $startPos, $endPos);
    }

    /**
     * Parses a single part of a character class (a literal, a range, or a char type).
     *
     * @throws ParserException
     */
    private function parseCharClassPart(): NodeInterface
    {
        $startToken = $this->current();
        $startPos = $startToken->position;

        $startNode = null;
        if ($this->match(TokenType::T_LITERAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);
            $startNode = new LiteralNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // \
            $startNode = new CharTypeNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            $len = 2 + mb_strlen($token->value); // \p or \P + value
            if (mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) {
                $len += 2; // for {}
            }
            $endPos = $startPos + $len;
            $startNode = new UnicodePropNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_UNICODE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);
            $startNode = new UnicodeNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_OCTAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);
            $startNode = new OctalNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_OCTAL_LEGACY)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // \
            $startNode = new OctalLegacyNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_RANGE)) {
            // This handles a literal "-" at the start (e.g. "[-a]")
            $endPos = $startPos + 1;

            return new LiteralNode($this->previous()->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_POSIX_CLASS)) {
            $token = $this->previous();
            // [[:class:]]
            $endPos = $startPos + mb_strlen($token->value) + 4; // [::]

            return new PosixClassNode($token->value, $startPos, $endPos);
        } else {
            $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
            throw new ParserException(\sprintf('Unexpected token "%s" (%s) in character class at %s. Expected literal, range, or character type.', $this->current()->value, $this->current()->type->value, $at));
        }

        // Check for a range (e.g., "a-z")
        if ($this->match(TokenType::T_RANGE)) {
            // Check if the range is followed by a "]" (e.g., "[a-]")
            if ($this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
                // This is a trailing "-", (e.g., "[a-]")
                // We treat the "-" as a literal.
                --$this->position; // Rewind to re-parse "-" as a literal in the next loop iteration

                return $startNode;
            }

            $endToken = $this->current(); // Peek at end node start
            $endNodeStartPos = $endToken->position;
            $endNode = null;

            if ($this->match(TokenType::T_LITERAL)) {
                $token = $this->previous();
                $endPos = $endNodeStartPos + mb_strlen($token->value);
                $endNode = new LiteralNode($token->value, $endNodeStartPos, $endPos);
            } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
                $token = $this->previous();
                $endPos = $endNodeStartPos + mb_strlen($token->value) + 1;
                $endNode = new CharTypeNode($token->value, $endNodeStartPos, $endPos);
            } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
                $token = $this->previous();
                $len = 2 + mb_strlen($token->value);
                if (mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) {
                    $len += 2;
                }
                $endPos = $endNodeStartPos + $len;
                $endNode = new UnicodePropNode($token->value, $endNodeStartPos, $endPos);
            } elseif ($this->match(TokenType::T_UNICODE)) {
                $token = $this->previous();
                $endPos = $endNodeStartPos + mb_strlen($token->value);
                $endNode = new UnicodeNode($token->value, $endNodeStartPos, $endPos);
            } elseif ($this->match(TokenType::T_OCTAL)) {
                $token = $this->previous();
                $endPos = $endNodeStartPos + mb_strlen($token->value);
                $endNode = new OctalNode($token->value, $endNodeStartPos, $endPos);
            } elseif ($this->match(TokenType::T_OCTAL_LEGACY)) {
                $token = $this->previous();
                $endPos = $endNodeStartPos + mb_strlen($token->value) + 1;
                $endNode = new OctalLegacyNode($token->value, $endNodeStartPos, $endPos);
            } else {
                // This means a range ending with a meta-char, e.g. [a-\]
                // We treat the "-" as a literal.
                --$this->position; // Rewind

                return $startNode;
            }

            return new RangeNode($startNode, $endNode, $startPos, $endNode->getEndPosition());
        }

        return $startNode;
    }

    /**
     * Checks if the current token matches the given type. If so, consumes it.
     */
    private function match(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        return false;
    }

    /**
     * Checks if the current token is a T_LITERAL with a specific value.
     */
    private function matchLiteral(string $value): bool
    {
        if ($this->checkLiteral($value)) {
            $this->advance();

            return true;
        }

        return false;
    }

    /**
     * Checks if the current token is a T_LITERAL with a specific value.
     */
    private function checkLiteral(string $value): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        $token = $this->current();

        return TokenType::T_LITERAL === $token->type && $token->value === $value;
    }

    /**
     * Consumes the current token, throwing an error if it doesn't match the expected type.
     *
     * @throws ParserException
     */
    private function consume(TokenType $type, string $error): Token
    {
        if ($this->check($type)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.')');
    }

    /**
     * Consumes the current token if it's a T_LITERAL with the given value.
     *
     * @throws ParserException
     */
    private function consumeLiteral(string $value, string $error): Token
    {
        if ($this->checkLiteral($value)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.' with value '.$this->current()->value.')');
    }

    /**
     * Checks the type of the current token without consuming it.
     */
    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return TokenType::T_EOF === $type;
        }

        return $this->current()->type === $type;
    }

    /**
     * Advances to the next token.
     */
    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            ++$this->position;
        }
    }

    /**
     * Checks if the parser has reached the end of the token stream.
     */
    private function isAtEnd(): bool
    {
        // We are only "at the end" when we hit the T_EOF token.
        return TokenType::T_EOF === $this->tokens[$this->position]->type;
    }

    /**
     * Gets the current token.
     */
    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * Gets the previously consumed token.
     */
    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }

    /**
     * Consumes characters from tokens as long as the predicate is true (adapted for tokens).
     */
    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while (!$this->isAtEnd() && $this->check(TokenType::T_LITERAL) && $predicate($this->current()->value)) {
            $value .= $this->current()->value;
            $this->advance();
        }

        return $value;
    }

    /**
     * Parses the name of a subroutine, handling different syntaxes.
     *
     * @throws ParserException
     */
    private function parseSubroutineName(): string
    {
        // Handles (?P>name) or (?&name)
        // We have already consumed '(?P>' or '(?&'

        $name = '';
        while (!$this->check(TokenType::T_GROUP_CLOSE) && !$this->isAtEnd()) {
            // A name can be any literal, but not special chars like '(', '[', etc.
            if ($this->check(TokenType::T_LITERAL)) {
                $name .= $this->current()->value;
                $this->advance();
            } else {
                // e.g., (?&name[...]) is invalid
                throw new ParserException('Unexpected token in subroutine name: '.$this->current()->value);
            }
        }

        if ('' === $name) {
            throw new ParserException('Expected subroutine name at position '.$this->current()->position);
        }

        return $name;
    }
}
