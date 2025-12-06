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
 * The Lexer (Tokenizer).
 *
 * This implementation uses a high-performance state machine based on PCRE.
 * It consumes tokens using `preg_match` with offsets to avoid memory allocation
 * overhead from substring operations.
 *
 * It preserves all tokens, including comments and quote mode markers, allowing
 * for a full reconstruction of the original pattern (Concrete Syntax Tree).
 */
final class Lexer
{
    /**
     * Regex to capture all possible tokens OUTSIDE of a character class.
     *
     * We use Nowdoc syntax (<<<'PCRE') to ensure readability and avoid
     * the "backslash hell" of standard PHP strings.
     */
    private const string REGEX_OUTSIDE = <<<'PCRE'
        /
          (?<T_COMMENT_OPEN>          \(\?\# )
        | (?<T_CALLOUT>               \(\?C [^)]* \) )
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
        | (?<T_UNICODE>               \\ x (?: [0-9a-fA-F]{2} | \{[0-9a-fA-F]+\} ) | \\ u\{[0-9a-fA-F]+\} )
        | (?<T_UNICODE_PROP>          \\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
        | (?<T_QUOTE_MODE_START>      \\ Q )
        | (?<T_QUOTE_MODE_END>        \\ E )
        | (?<T_LITERAL_ESCAPED>       \\ . ) # Any other escaped char (e.g. \., \*)
        
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
        | (?<T_UNICODE>          \\ x (?: [0-9a-fA-F]{2} | \{[0-9a-fA-F]+\} ) | \\ u\{[0-9a-fA-F]+\} )
        | (?<T_UNICODE_PROP>     \\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
        | (?<T_QUOTE_MODE_START> \\ Q )
        | (?<T_LITERAL_ESCAPED>  \\ . ) # Includes escaped ], -, ^
        
        # Must be last: Match any single character that wasn't matched above.
        | (?<T_LITERAL>          [^\\\\] )
        /xsuA
        PCRE;

    /**
     * Token priority list for the 'outside' state.
     * Maps regex group names to token types.
     */
    private const array TOKENS_OUTSIDE = [
        'T_COMMENT_OPEN',
        'T_CALLOUT',
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
     * Token priority list for the 'inside' state.
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

    // State flags
    private bool $inCharClass = false;

    private bool $inQuoteMode = false;

    private bool $inCommentMode = false;

    /**
     * @var int Marks the start of the current character class to handle special placement rules (e.g., ']' at start).
     */
    private int $charClassStartPosition = 0;

    /**
     * Converts a regular expression string into a consumable stream of tokens.
     *
     * Purpose: This method is the entry point for the lexical analysis phase. Its primary role
     * is to deconstruct the raw regex string into a structured `TokenStream`. This stream
     * acts as the input for the `Parser`, which then builds the Abstract Syntax Tree (AST).
     * As a contributor, think of this class as the foundation of the parsing process; it
     * translates unstructured text into a format the rest of the library can understand.
     *
     * The lexer is a state machine. It tracks its context (e.g., inside a character class,
     * a comment, or a quoted sequence) to apply different tokenization rules, ensuring
     * that characters like `[` or `(` are interpreted correctly based on their position.
     *
     * @param string $pattern The raw regular expression pattern to be tokenized. For correct
     *                        operation, this string must be UTF-8 encoded.
     *
     * @throws LexerException if the input `$pattern` is not a valid UTF-8 string, or if an
     *                        unrecognized sequence of characters is encountered during tokenization
     *
     * @return TokenStream An object containing the full sequence of `Token` objects. This
     *                     stream is ready to be consumed by the `Parser`.
     *
     * @example
     * ```php
     * $lexer = new Lexer();
     * $tokenStream = $lexer->tokenize('/(a|b)+/i');
     *
     * // The TokenStream can then be passed to the parser.
     * $parser = new Parser();
     * $ast = $parser->parse($tokenStream);
     * ```
     */
    public function tokenize(string $pattern): TokenStream
    {
        if (false === preg_match('//u', $pattern)) {
            throw LexerException::withContext('Input string is not valid UTF-8.', 0, $pattern);
        }

        $this->pattern = $pattern;
        // Use strlen (bytes) for preg_match cursor logic, as the 'u' modifier handles UTF-8 matching naturally.
        $this->length = \strlen($this->pattern);

        // Reset state
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->inCommentMode = false;
        $this->charClassStartPosition = 0;

        $tokens = [];

        while ($this->position < $this->length) {
            // 1. Handle "Tunnel" Modes (Quote & Comment)
            // @phpstan-ignore if.alwaysFalse (State changes via createTokenFromMatch on subsequent iterations)
            if ($this->inQuoteMode) {
                if ($token = $this->consumeQuoteMode()) {
                    $tokens[] = $token; // Keep for context
                }

                continue;
            }

            // @phpstan-ignore if.alwaysFalse (State changes via createTokenFromMatch on subsequent iterations)
            if ($this->inCommentMode) {
                if ($token = $this->consumeCommentMode()) {
                    $tokens[] = $token; // Keep for context
                }

                continue;
            }

            /*$specials = $this->inCharClass ? "[]\\-" : "[](){}*+?|.^$\\";
            $skip = strcspn($this->pattern, $specials, $this->position);

            if ($skip > 0) {
                // Emit a single literal token for the whole run
                $value = substr($this->pattern, $this->position, $skip);
                $tokens[] = new Token(TokenType::T_LITERAL, $value, $this->position);
                $this->position += $skip;
                continue;
            }*/

            // 2. Select Context-Aware Regex
            // @phpstan-ignore ternary.alwaysFalse (State changes via createTokenFromMatch on subsequent iterations)
            $regex = $this->inCharClass ? self::REGEX_INSIDE : self::REGEX_OUTSIDE;
            // @phpstan-ignore ternary.alwaysFalse (State changes via createTokenFromMatch on subsequent iterations)
            $tokenMap = $this->inCharClass ? self::TOKENS_INSIDE : self::TOKENS_OUTSIDE;

            // 3. Execute Matching
            $result = preg_match($regex, $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position);

            if (false === $result) {
                throw LexerException::withContext(\sprintf('PCRE Error during tokenization: %s', preg_last_error_msg()), $this->position, $this->pattern);
            }

            if (0 === $result) {
                $context = mb_substr($this->pattern, $this->position, 10);

                throw LexerException::withContext(\sprintf('Unable to tokenize pattern at position %d. Context: "%s..."', $this->position, $context), $this->position, $this->pattern);
            }

            /** @var string $matchedValue */
            $matchedValue = $matches[0];
            $startPos = $this->position;
            $this->position += \strlen($matchedValue);

            // 4. Create Token from Match and yield it
            $tokens[] = $this->createTokenFromMatch($tokenMap, $matches, $matchedValue, $startPos, $tokens);
        }

        // 5. Post-Processing Validation
        // @phpstan-ignore if.alwaysFalse (Reachable if pattern has unclosed character class)
        if ($this->inCharClass) {
            throw LexerException::withContext('Unclosed character class "]" at end of input.', $this->position, $this->pattern);
        }

        // @phpstan-ignore if.alwaysFalse (Reachable if pattern has unclosed comment)
        if ($this->inCommentMode) {
            throw LexerException::withContext('Unclosed comment ")" at end of input.', $this->position, $this->pattern);
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return new TokenStream($tokens, $pattern);
    }

    /**
     * Identifies which token type matched and creates the Token object.
     * Handles complex state transitions (e.g., entering char classes).
     *
     * @param array<string>                  $tokenMap
     * @param array<int|string, string|null> $matches
     * @param list<Token>                    $currentTokens
     */
    private function createTokenFromMatch(array $tokenMap, array $matches, string $matchedValue, int $startPos, array $currentTokens): Token
    {
        foreach ($tokenMap as $tokenName) {
            if (isset($matches[$tokenName])) {
                $type = TokenType::from(strtolower(substr($tokenName, 2)));

                if ($token = $this->handleStatefulToken($type, $matchedValue, $startPos, $currentTokens)) {
                    return $token;
                }

                $value = $this->extractTokenValue($type, $matchedValue, $matches);

                return new Token($type, $value, $startPos);
            }
        }

        // Should be unreachable
        throw LexerException::withContext(\sprintf('Lexer internal error: No known token matched at position %d.', $startPos), $startPos, $this->pattern);
    }

    /**
     * @param list<Token> $currentTokens
     */
    private function handleStatefulToken(TokenType $type, string $matchedValue, int $startPos, array $currentTokens): ?Token
    {
        if (TokenType::T_CHAR_CLASS_OPEN === $type) {
            return $this->handleCharClassOpen($startPos);
        }

        if (TokenType::T_CHAR_CLASS_CLOSE === $type) {
            return $this->handleCharClassClose($startPos, $currentTokens);
        }

        if (TokenType::T_COMMENT_OPEN === $type) {
            $this->inCommentMode = true;

            return new Token($type, '(?#', $startPos);
        }

        if (TokenType::T_QUOTE_MODE_START === $type) {
            $this->inQuoteMode = true;

            return new Token($type, '\Q', $startPos);
        }

        if ($this->inCharClass && TokenType::T_LITERAL === $type) {
            if ($this->isAtCharClassStart($startPos, $currentTokens) && '^' === $matchedValue) {
                return new Token(TokenType::T_NEGATION, '^', $startPos);
            }

            if (!$this->isAtCharClassStart($startPos, $currentTokens) && '-' === $matchedValue) {
                return new Token(TokenType::T_RANGE, '-', $startPos);
            }
        }

        return null;
    }

    /**
     * @param list<Token> $currentTokens
     */
    private function handleCharClassClose(int $startPos, array $currentTokens): Token
    {
        if ($this->isAtCharClassStart($startPos, $currentTokens)) {
            return new Token(TokenType::T_LITERAL, ']', $startPos);
        }

        $this->inCharClass = false;

        return new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', $startPos);
    }

    private function handleCharClassOpen(int $startPos): Token
    {
        $this->inCharClass = true;
        $this->charClassStartPosition = $startPos;

        return new Token(TokenType::T_CHAR_CLASS_OPEN, '[', $startPos);
    }

    /**
     * @param list<Token> $currentTokens
     */
    private function isAtCharClassStart(int $startPos, array $currentTokens): bool
    {
        $lastToken = end($currentTokens);

        return ($startPos === $this->charClassStartPosition + 1)
            || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);
    }

    /**
     * Consumes content inside \Q...\E.
     * Returns T_LITERAL for content, or T_QUOTE_MODE_END for \E.
     */
    private function consumeQuoteMode(): ?Token
    {
        // Search for \E or End of String
        if (!preg_match('/(.*?)((?:\\\\E)|$)/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            // Fallback: if parsing fails completely (unlikely), verify strict safety by resetting.
            $this->inQuoteMode = false;
            $this->position = $this->length;

            return null;
        }

        $literalText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        // 1. If there is content before \E, return it as a Literal
        if ('' !== $literalText) {
            $this->position += \strlen($literalText);

            return new Token(TokenType::T_LITERAL, $literalText, $startPos);
        }

        // 2. If we hit \E, return the end token and exit quote mode
        if ('\E' === $endSequence) {
            $this->inQuoteMode = false;
            $token = new Token(TokenType::T_QUOTE_MODE_END, '\E', $this->position);
            $this->position += 2; // Skip \E

            return $token;
        }

        // 3. End of string reached (PCRE allows unclosed \Q, it extends to EOF)
        $this->position = $this->length;

        // Note: We stay inQuoteMode logically until EOF, effectively behaving as literals.
        return null;
    }

    /**
     * Consumes content inside (?#...).
     * Returns T_LITERAL for content, or T_GROUP_CLOSE for ).
     */
    private function consumeCommentMode(): ?Token
    {
        // Search for ) or End of String
        if (!preg_match('/(.*?)(\)|$)/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            $this->inCommentMode = false;
            $this->position = $this->length;

            return null;
        }

        $commentText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        // 1. If there is text, return it as Literal content
        if ('' !== $commentText) {
            $this->position += \strlen($commentText);

            return new Token(TokenType::T_LITERAL, $commentText, $startPos);
        }

        // 2. If we hit ), return close token and exit comment mode
        if (')' === $endSequence) {
            $this->inCommentMode = false;
            $token = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position);
            $this->position++;

            return $token;
        }

        // 3. End of string reached (Error will be thrown by main loop checks)
        $this->position = $this->length;

        return null;
    }

    /**
     * Extracts and normalizes the value of a token.
     * Handles escape sequences like \n, \t, \xHH, etc.
     *
     * @param array<int|string, string|null> $matches
     */
    private function extractTokenValue(TokenType $type, string $matchedValue, array $matches): string
    {
        return match ($type) {
            TokenType::T_LITERAL_ESCAPED => match (substr($matchedValue, 1)) {
                't' => "\t",
                'n' => "\n",
                'r' => "\r",
                'f' => "\f",
                'v' => "\v",
                'e' => "\x1B", // Escape char (0x1B)
                'a' => "\x07", // Bell/Alarm char (0x07)
                default => substr($matchedValue, 1),
            },
            TokenType::T_PCRE_VERB => substr($matchedValue, 2, -1),
            TokenType::T_CALLOUT => substr($matchedValue, 3, -1),
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
    /*private function extractTokenValue(TokenType $type, string $match, array $matches): string
    {
        return match ($type) {
            TokenType::T_LITERAL_ESCAPED => match ($match) {
                '\t' => "\t",
                '\n' => "\n",
                '\r' => "\r",
                '\f' => "\f",
                '\e' => "\x07", // bell
                default => substr($match, 1),
            },
            TokenType::T_UNICODE_PROP => $this->normalizeUnicodeProp($match),
            TokenType::T_POSIX_CLASS => $matches['v_posix'][0] ?? '',
            default => $match,
        };
    }*/


    /**
     * Normalizes Unicode property notation to standard PCRE format.
     * Examples:
     * - \p{L}  -> L
     * - \P{L}  -> ^L (Negated)
     * - \P{^L} -> L  (Double negation)
     *
     * @param array<int|string, string|null> $matches
     */
    private function normalizeUnicodeProp(string $matchedValue, array $matches): string
    {
        $prop = $matches['v1_prop'] ?? $matches['v2_prop'] ?? '';
        $isUppercaseP = str_starts_with($matchedValue, '\\P');

        if ($isUppercaseP) {
            // Handle double negation: \P{^...} becomes ...
            if (str_starts_with($prop, '^')) {
                return substr($prop, 1);
            }

            // Handle single negation: \P{...} becomes ^...
            return '^'.$prop;
        }

        return $prop;
    }
}
