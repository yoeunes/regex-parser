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
    private readonly string $pattern;
    private int $position = 0;
    private readonly int $length;
    private bool $inCharClass = false;
    private bool $inQuoteMode = false;
    private int $charClassStartPosition = 0;

    /**
     * Regex to capture all possible tokens OUTSIDE of a character class.
     * Order is crucial. Longer, more specific tokens must come first.
     * NOTE: All literal backslashes must be quadruple-escaped (\\\\)
     * to be a literal backslash inside the single-quoted PHP string.
     */
    private const REGEX_OUTSIDE = '/
        (?<T_COMMENT_OPEN>          \(\?\# )
        | (?<T_PCRE_VERB>           \(\* [^)]+ \) )
        | (?<T_GROUP_MODIFIER_OPEN> \(\? )
        | (?<T_GROUP_OPEN>          \( )
        | (?<T_GROUP_CLOSE>         \) )
        | (?<T_CHAR_CLASS_OPEN>     \[ )
        | (?<T_QUANTIFIER>          (?: [\*\+\?] | \{\d+(?:,\d*)?\} ) [\?\+]? )
        | (?<T_ALTERNATION>         \| )
        | (?<T_DOT>                 \. )
        | (?<T_ANCHOR>              \^ | \$ )
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
        | (?<T_LITERAL_ESCAPED>     \\\\ . )
        | (?<T_LITERAL>             . )
    /xsuA'; // s: . matches \n, u: unicode, A: anchored

    /**
     * Regex to capture tokens INSIDE a character class.
     */
    private const REGEX_INSIDE = '/
        (?<T_CHAR_CLASS_CLOSE> \] )
        | (?<T_POSIX_CLASS>      \[ \: (?<v_posix> \^? [a-zA-Z]+) \: \] )
        | (?<T_CHAR_TYPE>      \\\\ [dswDSWhvR] )
        | (?<T_OCTAL_LEGACY>   \\\\ 0[0-7]{0,2} )
        | (?<T_OCTAL>          \\\\ o\{[0-7]+\} )
        | (?<T_UNICODE>        \\\\ x[0-9a-fA-F]{2} | \\\\ u\{[0-9a-fA-F]+\} )
        | (?<T_UNICODE_PROP>   \\\\ [pP] (?: \{ (?<v1_prop> \^? [a-zA-Z0-9_]+) \} | (?<v2_prop> [a-zA-Z]) ) )
        | (?<T_LITERAL_ESCAPED> \\\\ . )
        | (?<T_LITERAL>        . )
    /xsuA';

    public function __construct(string $pattern)
    {
        if (!mb_check_encoding($pattern, 'UTF-8')) {
            throw new LexerException('Input string is not valid UTF-8.');
        }

        $this->pattern = $pattern;
        // We use strlen (bytes) for the preg_match cursor,
        // as the 'u' (unicode) flag handles the character matching.
        $this->length = \strlen($this->pattern);
    }

    /**
     * @return array<Token>
     *
     * @throws LexerException
     */
    public function tokenize(): array
    {
        $tokens = [];
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->charClassStartPosition = 0; // Reset state

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

            // Add PREG_UNMATCHED_AS_NULL flag.
            // This ensures unmatched named groups are 'null', not '""'.
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

            // Context-sensitive check for ']' at the start of a char class.
            if ($this->inCharClass && ']' === $matchedValue) {
                $lastToken = \count($tokens) > 0 ? $tokens[\count($tokens) - 1] : null;
                $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                    || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                if ($isAtStart) {
                    // It's a literal ']', not a closing bracket.
                    $tokens[] = new Token(TokenType::T_LITERAL, ']', $startPos);
                    continue; // Skip the main foreach loop
                }
            }

            $token = null;
            /**
             * @var string|int  $key
             * @var string|null $value
             */
            foreach ($matches as $key => $value) {
                // We only care about named groups that have matched
                // $value can be null here thanks to PREG_UNMATCHED_AS_NULL
                if (\is_int($key) || null === $value || '' === $value) {
                    continue;
                }

                // 'T_LITERAL' -> TokenType::T_LITERAL
                $type = TokenType::from(strtolower(substr($key, 2)));

                // Handle state-changing tokens first
                if (TokenType::T_CHAR_CLASS_OPEN === $type) {
                    $this->inCharClass = true;
                    $this->charClassStartPosition = $startPos;
                    $token = new Token($type, '[', $startPos);
                    break;
                }

                if (TokenType::T_CHAR_CLASS_CLOSE === $type) {
                    $this->inCharClass = false;
                    $token = new Token($type, ']', $startPos);
                    break;
                }

                if (TokenType::T_QUOTE_MODE_START === $type) {
                    $this->inQuoteMode = true;
                    // No token is emitted, just a state change
                    break;
                }

                if (TokenType::T_QUOTE_MODE_END === $type) {
                    $this->inQuoteMode = false;
                    // No token is emitted, just a state change
                    break;
                }

                // Handle context-sensitive tokens *inside* a char class
                if ($this->inCharClass) {
                    $lastToken = \count($tokens) > 0 ? $tokens[\count($tokens) - 1] : null;

                    $isAtStart = ($startPos === $this->charClassStartPosition + 1)
                        || ($startPos === $this->charClassStartPosition + 2 && $lastToken && TokenType::T_NEGATION === $lastToken->type);

                    // T_NEGATION: Only a '^' at the very start is a negation
                    if (TokenType::T_LITERAL === $type && '^' === $matchedValue && $isAtStart) {
                        $token = new Token(TokenType::T_NEGATION, '^', $startPos);
                        break;
                    }

                    // T_RANGE: Only a '-' *not* at the start is a range
                    // (The parser will validate if it's not at the end)
                    if (TokenType::T_LITERAL === $type && '-' === $matchedValue && !$isAtStart) {
                        $token = new Token(TokenType::T_RANGE, '-', $startPos);
                        break;
                    }
                }

                // Handle T_LITERAL_ESCAPED: it should be tokenized as a T_LITERAL
                if (TokenType::T_LITERAL_ESCAPED === $type) {
                    $type = TokenType::T_LITERAL;
                }

                // Default case: Create a standard token
                $tokenValue = $this->extractTokenValue($type, $matchedValue, $matches);
                $token = new Token($type, $tokenValue, $startPos);
                break;
            }

            if ($token) {
                $tokens[] = $token;
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
        // Also add PREG_UNMATCHED_AS_NULL here for consistency
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
        // Handle T_LITERAL_ESCAPED first: it becomes a T_LITERAL
        if (TokenType::T_LITERAL === $type) {
            if (($matches['T_LITERAL_ESCAPED'] ?? null) !== null) {
                // It was an escaped literal. Return just the character.
                return substr($matchedValue, 1);
            }

            // It was a normal literal.
            return $matchedValue;
        }

        return match ($type) {
            TokenType::T_PCRE_VERB => substr($matchedValue, 2, -1),
            // Note: T_LITERAL is handled above
            TokenType::T_ASSERTION, TokenType::T_CHAR_TYPE, TokenType::T_KEEP => substr($matchedValue, 1),
            // \k<name> is stored as \k<name>, but \2 is stored as 2
            TokenType::T_BACKREF => ($matches['v_backref_num'] ?? null) !== null ? $matches['v_backref_num'] : $matchedValue,
            TokenType::T_G_REFERENCE, TokenType::T_OCTAL => $matchedValue,
            TokenType::T_OCTAL_LEGACY => substr($matchedValue, 1), // \01 -> 01
            TokenType::T_UNICODE => $matchedValue,
            // These types need to extract the sub-group
            TokenType::T_POSIX_CLASS => (string) ($matches['v_posix'] ?? ''), // from [[:(alnum):]]
            TokenType::T_UNICODE_PROP => (string) ($matches['v1_prop'] ?? $matches['v2_prop'] ?? ''), // from \p{(L)} or \p(L)
            // Default: the matched value is correct
            default => $matchedValue,
        };
    }
}
