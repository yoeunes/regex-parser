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
 * The Lexer (Tokenizer) - High-Performance State-Machine Version.
 *
 * This implementation uses preg_match() in a loop to "consume"
 * whole tokens, instead of iterating character-by-character.
 * This is the approach used by Twig, Doctrine, and other
 * high-performance parsers.
 */
class Lexer
{
    /**
     * Regex to capture all possible tokens OUTSIDE of a character class.
     * Order is crucial. Longer, more specific tokens must come first.
     * NOTE: All literal backslashes must be quadruple-escaped (\\\\)
     * to be a literal backslash inside the single-quoted PHP string.
     */
    private const REGEX_OUTSIDE = '/
        (?<T_COMMENT_OPEN>          \(\?\# )
      | (?<T_PCRE_VERB>           \(\* [^)]+ \) ) # Ex: (*FAIL), (*MARK:foo)
      | (?<T_GROUP_MODIFIER_OPEN> \(\? )
      | (?<T_GROUP_OPEN>          \( )
      | (?<T_GROUP_CLOSE>         \) )
      | (?<T_CHAR_CLASS_OPEN>     \[ )
      | (?<T_QUANTIFIER>          (?: [\*\+\?] | \{\d+(?:,\d*)?\} ) [\?\+]? )
      | (?<T_ALTERNATION>         \| )
      | (?<T_DOT>                 \. )
      | (?<T_ANCHOR>              \^ | \$ )
      
      # Escaped sequences (must precede T_LITERAL)
      | (?<T_ASSERTION>           \\\\ [AzZGbB] )
      | (?<T_KEEP>                \\\\ K )
      | (?<T_CHAR_TYPE>           \\\\ [dswDSWhvR] )
      | (?<T_G_REFERENCE>         \\\\ g (?: \{[a-zA-Z0-9_+-]+\} | <[a-zA-Z0-9_]+> | [0-9+-]+ )? )
      | (?<T_BACKREF>             \\\\ (?: k(?:<[a-zA-Z0-9_]+> | \{[a-zA-Z0-9_]+\}) | (?<v_backref_num> [1-9]\d*) ) )
      | (?<T_OCTAL_LEGACY>        \\\\ 0[0-7]{0,2} )
      | (?<T_OCTAL>               \\\\ o\{[0-7]+\} )
      | (?<T_UNICODE>             \\\\ x[0-9a-fA-F]{2} | \\\\ u\{[0-9a-fA-F]+\} )
      | (?<T_UNICODE_PROP>        \\\\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
      | (?<T_QUOTE_MODE_START>    \\\\ Q )
      | (?<T_QUOTE_MODE_END>      \\\\ E )
      | (?<T_LITERAL_ESCAPED>     \\\\ . ) # Any other escaped char
      
      # Must be last: Match any single character that wasn\'t matched above.
      | (?<T_LITERAL>             . )
    /xsuA'; // s: . matches \n, u: unicode, A: anchored

    /**
     * Regex to capture tokens INSIDE a character class.
     */
    private const REGEX_INSIDE = '/
        (?<T_CHAR_CLASS_CLOSE> \] )
      | (?<T_POSIX_CLASS>      \[ \: (?<v_posix> \^? [a-zA-Z]+) \: \] )
      
      # Escaped sequences
      | (?<T_CHAR_TYPE>        \\\\ [dswDSWhvR] )
      | (?<T_OCTAL_LEGACY>     \\\\ 0[0-7]{0,2} )
      | (?<T_OCTAL>            \\\\ o\{[0-7]+\} )
      | (?<T_UNICODE>          \\\\ x[0-9a-fA-F]{2} | \\\\ u\{[0-9a-fA-F]+\} )
      | (?<T_UNICODE_PROP>     \\\\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
      | (?<T_LITERAL_ESCAPED>  \\\\ . ) # Includes escaped \], \-, \^
      
      # Must be last: Match any single character that wasn\'t matched above.
      | (?<T_LITERAL>          . )
    /xsuA';

    /**
     * Defines the explicit order of token matching for the 'outside' state.
     * This matches the alternation order in REGEX_OUTSIDE.
     *
     * @var list<string>
     */
    private const TOKEN_NAMES_OUTSIDE = [
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
     * Defines the explicit order of token matching for the 'inside' state.
     *
     * @var list<string>
     */
    private const TOKEN_NAMES_INSIDE = [
        'T_CHAR_CLASS_CLOSE',
        'T_POSIX_CLASS',
        'T_CHAR_TYPE',
        'T_OCTAL_LEGACY',
        'T_OCTAL',
        'T_UNICODE',
        'T_UNICODE_PROP',
        'T_LITERAL_ESCAPED',
        'T_LITERAL',
    ];

    private string $pattern;

    private int $position = 0;

    private int $length;

    private bool $inCharClass = false;

    private bool $inQuoteMode = false;

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
        // We use strlen (bytes) for the preg_match cursor,
        // as the 'u' (unicode) flag handles the character matching.
        $this->length = \strlen($this->pattern);

        // Reset state
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->charClassStartPosition = 0;
    }

    /**
     * @throws LexerException
     *
     * @return array<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];
        // Ensure state is clean before tokenizing
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->charClassStartPosition = 0;

        while ($this->position < $this->length) {
            // State 1: Quote Mode (\Q...\E)
            // This state takes precedence and has its own simple logic.
            if ($this->inQuoteMode) {
                $token = $this->lexQuoteMode();
                if ($token) {
                    $tokens[] = $token;
                }

                continue; // Re-evaluate loop with new state (inQuoteMode might be false)
            }

            // State 2: Character Class or Default
            $regex = $this->inCharClass ? self::REGEX_INSIDE : self::REGEX_OUTSIDE;
            $tokenNames = $this->inCharClass ? self::TOKEN_NAMES_INSIDE : self::TOKEN_NAMES_OUTSIDE;

            if (!preg_match($regex, $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
                // Any other match failure
                throw new LexerException(\sprintf('Unable to tokenize pattern at position %d: "%s"...', $this->position, mb_substr($this->pattern, $this->position, 10)));
            }

            /** @var string $matchedValue */
            $matchedValue = $matches[0];
            $startPos = $this->position;

            // Advance the cursor by the byte-length of the matched token
            $this->position += \strlen($matchedValue);

            // --- State Management & Token Creation ---
            $tokenFound = false;

            foreach ($tokenNames as $tokenName) {
                // Find the first (and only) token that matched.
                if (isset($matches[$tokenName])) {
                    $type = TokenType::from(strtolower(substr($tokenName, 2)));

                    // Handle state-changing tokens first
                    if (TokenType::T_CHAR_CLASS_OPEN === $type) {
                        $this->inCharClass = true;
                        $this->charClassStartPosition = $startPos;
                        $tokens[] = new Token($type, '[', $startPos);
                        $tokenFound = true;

                        break;
                    }

                    if (TokenType::T_CHAR_CLASS_CLOSE === $type) {
                        // Context-sensitive check: Is ']' a literal?
                        $lastToken = \count($tokens) > 0 ? $tokens[\count($tokens) - 1] : null;
                        $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                            || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                        if ($isAtStart) {
                            // It's a literal ']', not a closing bracket.
                            $tokens[] = new Token(TokenType::T_LITERAL, ']', $startPos);
                        } else {
                            // It's a closing bracket.
                            $this->inCharClass = false;
                            $tokens[] = new Token($type, ']', $startPos);
                        }
                        $tokenFound = true;

                        break;
                    }

                    if (TokenType::T_QUOTE_MODE_START === $type) {
                        $this->inQuoteMode = true;
                        $tokenFound = true; // No token is emitted, just a state change

                        break;
                    }

                    if (TokenType::T_QUOTE_MODE_END === $type) {
                        $this->inQuoteMode = false;
                        $tokenFound = true; // No token is emitted, just a state change

                        break;
                    }

                    // Handle context-sensitive tokens *inside* a char class
                    if ($this->inCharClass) {
                        $lastToken = \count($tokens) > 0 ? $tokens[\count($tokens) - 1] : null;
                        $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                            || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                        // T_NEGATION: Only a '^' at the very start is a negation
                        if (TokenType::T_LITERAL === $type && '^' === $matchedValue && $isAtStart) {
                            $tokens[] = new Token(TokenType::T_NEGATION, '^', $startPos);
                            $tokenFound = true;

                            break;
                        }

                        // T_RANGE: Only a '-' *not* at the start is a range
                        if (TokenType::T_LITERAL === $type && '-' === $matchedValue && !$isAtStart) {
                            $tokens[] = new Token(TokenType::T_RANGE, '-', $startPos);
                            $tokenFound = true;

                            break;
                        }
                    }

                    // if (TokenType::T_LITERAL_ESCAPED === $type) {
                    //     $type = TokenType::T_LITERAL;
                    // }

                    // Default case: Create a standard token
                    $tokenValue = $this->extractTokenValue($type, $matchedValue, $matches);
                    $tokens[] = new Token($type, $tokenValue, $startPos);
                    $tokenFound = true;

                    break;
                }
            } // end foreach tokenName

            if (!$tokenFound) {
                // This should not happen if the regex patterns are correct
                // and cover all cases (e.g. \Q or \E was matched)
                throw new LexerException('Lexer internal error: No token was processed at position '.$startPos);
            }
        } // end while

        // Check for a trailing backslash that was not consumed
        if (!$this->inQuoteMode && $this->position === $this->length && str_ends_with($this->pattern, '\\')) {
            throw new LexerException('Trailing backslash at position '.($this->length - 1));
        }

        if ($this->inCharClass) {
            throw new LexerException('Unclosed character class "]" at end of input.');
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
    }

    /**
     * Handles tokenization inside \Q...\E (quote mode).
     *
     * This method finds the *next* \E or the end of the string.
     * All text in between is emitted as a single literal token.
     */
    private function lexQuoteMode(): ?Token
    {
        // Note: We use /s (dotall) here, not /x
        if (!preg_match('/(.*?)((?:\\\\E)|$)/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            // This should be logically impossible if lexQuoteMode is called.
            // As a fallback, we exit quote mode and stop.
            $this->inQuoteMode = false;
            $this->position = $this->length;

            return null;
        }

        $literalText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        if ('' !== $literalText) {
            // We found text before \E or end. Emit it as a literal.
            $this->position += \strlen($literalText);

            return new Token(TokenType::T_LITERAL, $literalText, $startPos);
        }

        // We are at \E or end of string.
        if ('\E' === $endSequence) {
            $this->position += 2; // Advance past \E
            $this->inQuoteMode = false; // Exit quote mode
        } else {
            // End of string, \E was never found.
            $this->position = $this->length;
            // We remain inQuoteMode (this is PCRE's behavior)
        }

        return null; // No token emitted, just state change or end.
    }

    /**
     * Extracts the simple value from a matched token string.
     * (e.g., "\d" -> "d", "(*FAIL)" -> "FAIL", "\." -> ".").
     *
     * @param array<int|string, string|null> $matches
     */
    private function extractTokenValue(TokenType $type, string $matchedValue, array $matches): string
    {
        return match ($type) {
            TokenType::T_LITERAL_ESCAPED => substr($matchedValue, 1), // \. -> .
            TokenType::T_PCRE_VERB => substr($matchedValue, 2, -1),
            TokenType::T_ASSERTION, TokenType::T_CHAR_TYPE, TokenType::T_KEEP => substr($matchedValue, 1),
            TokenType::T_BACKREF => ($matches['v_backref_num'] ?? null) !== null ? $matches['v_backref_num'] : $matchedValue,
            TokenType::T_OCTAL_LEGACY => substr($matchedValue, 1), // \01 -> 01
            TokenType::T_POSIX_CLASS => (string) ($matches['v_posix'] ?? ''), // from [[:(alnum):]]
            TokenType::T_UNICODE_PROP => (string) ($matches['v1_prop'] ?? $matches['v2_prop'] ?? ''), // from \p{(L)} or \p(L)
            // Default: the matched value is correct (T_LITERAL, T_OCTAL, T_UNICODE, etc.)
            default => $matchedValue,
        };
    }
}
