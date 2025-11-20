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

namespace RegexParser;

use RegexParser\Exception\LexerException;

/**
 * The Lexer (Tokenizer)
 *
 * This implementation uses preg_match() with offsets in a loop to "consume"
 * tokens. This avoids the overhead of character-by-character iteration in PHP.
 *
 * This architectural pattern (Stateful Regex Lexer) is similar to the one
 * used by the Twig template engine.
 */
final class Lexer
{
    /**
     * Regex to capture all possible tokens OUTSIDE of a character class.
     */
    private const string REGEX_OUTSIDE = <<<'PCRE'
        /
          (?<T_COMMENT_OPEN>          \(\?\# )
        | (?<T_PCRE_VERB>             \(\* [^)]+ \) ) # Ex: (*FAIL), (*MARK:foo)
        | (?<T_GROUP_MODIFIER_OPEN>   \(\? )
        | (?<T_GROUP_OPEN>            \( )
        | (?<T_GROUP_CLOSE>           \) )
        | (?<T_CHAR_CLASS_OPEN>       \[ )
        | (?<T_QUANTIFIER>            (?: [\*\+\?] | \{\d+(?:,\d*)?\} ) [\?\+]? )
        | (?<T_ALTERNATION>           \| )
        | (?<T_DOT>                   \. )
        | (?<T_ANCHOR>                \^ | \$ )
        
        # Escaped sequences (must precede T_LITERAL)
        | (?<T_ASSERTION>             \\ [AzZGbB] )
        | (?<T_KEEP>                  \\ K )
        | (?<T_CHAR_TYPE>             \\ [dswDSWhvR] )
        | (?<T_G_REFERENCE>           \\ g (?: \{[a-zA-Z0-9_+-]+\} | <[a-zA-Z0-9_]+> | [0-9+-]+ )? )
        | (?<T_BACKREF>               \\ (?: k(?:<[a-zA-Z0-9_]+> | \{[a-zA-Z0-9_]+\}) | (?<v_backref_num> [1-9]\d*) ) )
        | (?<T_OCTAL_LEGACY>          \\ 0[0-7]{0,2} )
        | (?<T_OCTAL>                 \\ o\{[0-7]+\} )
        | (?<T_UNICODE>               \\ x[0-9a-fA-F]{2} | \\ u\{[0-9a-fA-F]+\} )
        | (?<T_UNICODE_PROP>          \\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
        | (?<T_QUOTE_MODE_START>      \\ Q )
        | (?<T_QUOTE_MODE_END>        \\ E )
        | (?<T_LITERAL_ESCAPED>       \\ . ) # Any other escaped char
        
        # Must be last: Match any single character that wasn't matched above.
        | (?<T_LITERAL>               [^\\\\] )
        /xsuA
        PCRE;

    /**
     * Regex to capture tokens INSIDE a character class.
     */
    private const string REGEX_INSIDE = <<<'PCRE'
        /
          (?<T_CHAR_CLASS_CLOSE> \] )
        | (?<T_POSIX_CLASS>      \[ \: (?<v_posix> \^? [a-zA-Z]+) \: \] )
        
        # Escaped sequences
        | (?<T_CHAR_TYPE>        \\ [dswDSWhvR] )
        | (?<T_OCTAL_LEGACY>     \\ 0[0-7]{0,2} )
        | (?<T_OCTAL>            \\ o\{[0-7]+\} )
        | (?<T_UNICODE>          \\ x[0-9a-fA-F]{2} | \\ u\{[0-9a-fA-F]+\} )
        | (?<T_UNICODE_PROP>     \\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
        | (?<T_QUOTE_MODE_START> \\ Q )
        | (?<T_LITERAL_ESCAPED>  \\ . ) # Includes escaped ], -, ^
        
        # Must be last: Match any single character that wasn't matched above.
        | (?<T_LITERAL>          [^\\\\] )
        /xsuA
        PCRE;

    /**
     * Prioritized list of token names for the 'outside' state.
     */
    private const array TOKENS_OUTSIDE = [
        'T_COMMENT_OPEN',
        'T_PCRE_VERB',
        'T_GROUP_MODIFIER_OPEN',
        'T_GROUP_OPEN',
        'T_GROUP_CLOSE',
        'T_CHAR_CLASS_OPEN',
        'T_QUANTIFIER',
        'T_ALTERNATION',
        'T_DOT',
        'T_ANCHOR',
        'T_ASSERTION',
        'T_KEEP',
        'T_CHAR_TYPE',
        'T_G_REFERENCE',
        'T_BACKREF',
        'T_OCTAL_LEGACY',
        'T_OCTAL',
        'T_UNICODE',
        'T_UNICODE_PROP',
        'T_QUOTE_MODE_START',
        'T_QUOTE_MODE_END',
        'T_LITERAL_ESCAPED',
        'T_LITERAL',
    ];

    /**
     * Prioritized list of token names for the 'inside' state.
     */
    private const array TOKENS_INSIDE = [
        'T_CHAR_CLASS_CLOSE',
        'T_POSIX_CLASS',
        'T_CHAR_TYPE',
        'T_OCTAL_LEGACY',
        'T_OCTAL',
        'T_UNICODE',
        'T_UNICODE_PROP',
        'T_QUOTE_MODE_START',
        'T_LITERAL_ESCAPED',
        'T_LITERAL',
    ];

    private string $pattern;

    private int $position = 0;

    private int $length = 0;

    private bool $inCharClass = false;

    private bool $inQuoteMode = false;

    private bool $inCommentMode = false;

    private int $charClassStartPosition = 0;

    public function __construct(string $pattern)
    {
        $this->reset($pattern);
    }

    /**
     * Resets the lexer with a new pattern string.
     */
    public function reset(string $pattern): void
    {
        if (!mb_check_encoding($pattern, 'UTF-8')) {
            throw new LexerException('Input string is not valid UTF-8.');
        }

        $this->pattern = $pattern;
        // Use strlen (bytes) for preg_match cursor, as 'u' flag handles UTF-8 chars matching.
        $this->length = \strlen($this->pattern);

        // Reset state
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->inCommentMode = false;
        $this->charClassStartPosition = 0;
    }

    /**
     * @throws LexerException
     *
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->position < $this->length) {
            // 1. Handle "Tunnel" Modes (Quote & Comment)
            // These modes consume raw text until a terminator, ignoring standard token rules.

            if ($this->inQuoteMode) {
                if ($token = $this->consumeQuoteMode()) {
                    $tokens[] = $token;
                }

                continue;
            }

            if ($this->inCommentMode) {
                if ($token = $this->consumeCommentMode()) {
                    $tokens[] = $token;
                }

                continue;
            }

            // 2. Select Regex based on Context
            $regex = $this->inCharClass ? self::REGEX_INSIDE : self::REGEX_OUTSIDE;
            $tokenMap = $this->inCharClass ? self::TOKENS_INSIDE : self::TOKENS_OUTSIDE;

            // 3. Match next token
            // PREG_UNMATCHED_AS_NULL is crucial to differentiate empty matches from non-matches
            $result = preg_match($regex, $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position);

            if (false === $result) {
                // PCRE Internal Error (e.g. JIT limit, recursion limit)
                throw new LexerException(\sprintf('PCRE Error during tokenization: %s', preg_last_error_msg()));
            }

            if (0 === $result) {
                // Should effectively never happen given the catch-all T_LITERAL,
                // but serves as a safety net for malformed UTF-8 or engine quirks.
                $context = mb_substr($this->pattern, $this->position, 10);

                throw new LexerException(\sprintf('Unable to tokenize pattern at position %d. Context: "%s..."', $this->position, $context));
            }

            /** @var string $matchedValue */
            $matchedValue = $matches[0];
            $startPos = $this->position;
            $this->position += \strlen($matchedValue);

            // 4. Identify and Create Token
            $token = $this->createTokenFromMatch($tokenMap, $matches, $matchedValue, $startPos, $tokens);
            $tokens[] = $token;
        }

        if ($this->inCharClass) {
            throw new LexerException('Unclosed character class "]" at end of input.');
        }

        if ($this->inCommentMode) {
            throw new LexerException('Unclosed comment ")" at end of input.');
        }

        // Append EOF token to signal parsing completion
        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
    }

    /**
     * Identifies which named group matched and creates the corresponding Token.
     * Handles state transitions (entering/exiting char classes, comments, etc.).
     *
     * @param array<string>              $tokenMap
     * @param array<string, string|null> $matches
     * @param list<Token>                $currentTokens
     */
    private function createTokenFromMatch(array $tokenMap, array $matches, string $matchedValue, int $startPos, array $currentTokens): Token
    {
        foreach ($tokenMap as $tokenName) {
            if (isset($matches[$tokenName])) {
                $type = TokenType::from(strtolower(substr($tokenName, 2)));

                // Handle State Transitions
                if (TokenType::T_CHAR_CLASS_OPEN === $type) {
                    $this->inCharClass = true;
                    $this->charClassStartPosition = $startPos;

                    return new Token($type, '[', $startPos);
                }

                if (TokenType::T_CHAR_CLASS_CLOSE === $type) {
                    // Edge Case: ']' immediately after '[' or '[^' is treated as a literal ']'
                    $lastToken = end($currentTokens);
                    $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                        || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                    if ($isAtStart) {
                        return new Token(TokenType::T_LITERAL, ']', $startPos);
                    }
                    $this->inCharClass = false;

                    return new Token($type, ']', $startPos);
                }

                if (TokenType::T_COMMENT_OPEN === $type) {
                    $this->inCommentMode = true;

                    return new Token($type, '(?#', $startPos);
                }

                if (TokenType::T_QUOTE_MODE_START === $type) {
                    $this->inQuoteMode = true;

                    // We return a literal token for \Q to represent it in the stream,
                    // though it won't be part of the compiled output directly.
                    // Wait, logic dictates we shouldn't output a token if we just switch mode?
                    // Original code: break and continue loop.
                    // Refactored: Let's return the token so the parser sees it, or we can handle it.
                    // To stick to your original logic where no token is emitted for state change:
                    // Note: This method expects to return a Token.
                    // For \Q, usually we want to consume it silently?
                    // Actually, let's return a T_QUOTE_MODE_START token. The parser can choose to ignore it or use it.
                    return new Token($type, '\Q', $startPos);
                }

                // Context-sensitive tokens inside char class
                if ($this->inCharClass) {
                    $lastToken = end($currentTokens);
                    $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                        || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                    // '^' is negation only at start
                    if (TokenType::T_LITERAL === $type && '^' === $matchedValue && $isAtStart) {
                        return new Token(TokenType::T_NEGATION, '^', $startPos);
                    }
                    // '-' is range only if NOT at start
                    if (TokenType::T_LITERAL === $type && '-' === $matchedValue && !$isAtStart) {
                        return new Token(TokenType::T_RANGE, '-', $startPos);
                    }
                }

                // Standard Token Creation
                $value = $this->extractTokenValue($type, $matchedValue, $matches);

                return new Token($type, $value, $startPos);
            }
        }

        // Should be unreachable due to regex design
        throw new LexerException(\sprintf('Lexer internal error: No known token matched at position %d.', $startPos));
    }

    /**
     * Consumes content until \E or End of String.
     */
    private function consumeQuoteMode(): ?Token
    {
        // /s modifier (dotall) ensures . matches newlines
        if (!preg_match('/(.*?)((?:\\\\E)|$)/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            // Fallback safety
            $this->inQuoteMode = false;
            $this->position = $this->length;

            return null;
        }

        $literalText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        if ('' !== $literalText) {
            $this->position += \strlen($literalText);

            return new Token(TokenType::T_LITERAL, $literalText, $startPos);
        }

        if ('\E' === $endSequence) {
            $this->position += 2; // Advance past \E
            $this->inQuoteMode = false;

            // Optional: emit T_QUOTE_MODE_END if you want full fidelity
            return new Token(TokenType::T_QUOTE_MODE_END, '\E', $this->position - 2);
        }

        // End of string reached without \E
        $this->position = $this->length;

        return null;
    }

    /**
     * Consumes content until ')' or End of String.
     */
    private function consumeCommentMode(): ?Token
    {
        if (!preg_match('/(.*?)(\)|$)/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            $this->inCommentMode = false;
            $this->position = $this->length;

            return null;
        }

        $commentText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        if ('' !== $commentText) {
            $this->position += \strlen($commentText);

            return new Token(TokenType::T_LITERAL, $commentText, $startPos);
        }

        if (')' === $endSequence) {
            $this->inCommentMode = false;
            $token = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position);
            $this->position++;

            return $token;
        }

        $this->position = $this->length;

        return null;
    }

    private function extractTokenValue(TokenType $type, string $matchedValue, array $matches): string
    {
        return match ($type) {
            TokenType::T_LITERAL_ESCAPED => match (substr($matchedValue, 1)) {
                't' => "\t",
                'n' => "\n",
                'r' => "\r",
                'f' => "\f",
                'v' => "\v",
                'e' => "\x1B", // Escape char
                'a' => "\x07", // Alarm/Bell
                default => substr($matchedValue, 1),
            },
            TokenType::T_PCRE_VERB => substr($matchedValue, 2, -1),
            TokenType::T_ASSERTION,
            TokenType::T_CHAR_TYPE,
            TokenType::T_KEEP => substr($matchedValue, 1),
            TokenType::T_BACKREF => $matches['v_backref_num'] ?? $matchedValue,
            TokenType::T_OCTAL_LEGACY => substr($matchedValue, 1),
            TokenType::T_POSIX_CLASS => $matches['v_posix'] ?? '',
            TokenType::T_UNICODE_PROP => $this->normalizeUnicodeProp($matchedValue, $matches),
            default => $matchedValue,
        };
    }

    private function normalizeUnicodeProp(string $matchedValue, array $matches): string
    {
        $prop = $matches['v1_prop'] ?? $matches['v2_prop'] ?? '';
        $isUppercaseP = str_starts_with($matchedValue, '\\P');

        if ($isUppercaseP) {
            if (str_starts_with($prop, '^')) {
                return substr($prop, 1); // Double negation: \P{^L} -> L
            }

            return '^'.$prop; // Negation: \P{L} -> ^L
        }

        return $prop;
    }
}
