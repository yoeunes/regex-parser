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

/**
 * Provides a consumable stream of tokens with lookahead and rewind capabilities.
 *
 * Purpose: This class is a crucial abstraction layer between the `Lexer` and the `Parser`.
 * It wraps the raw array of tokens and provides a stateful, iterable interface. The `Parser`
 * uses this stream to consume tokens one by one (`next()`), look at upcoming tokens without
 * consuming them (`peek()`), and even go back to a previous state (`rewind()`). This
 * enables the recursive descent parsing strategy by allowing the parser to make decisions
 * based on the current and upcoming tokens.
 */
class TokenStream
{
    /**
     * Buffer for lookahead tokens.
     *
     * @var array<int, Token>
     */
    private array $buffer = [];

    /**
     * History of consumed tokens for rewinding.
     *
     * @var array<int, Token>
     */
    private array $history = [];

    /**
     * Current position in the stream.
     */
    private int $position = 0;

    /**
     * Track if generator is exhausted.
     */
    private bool $exhausted = false;

    /**
     * Initializes the token stream.
     *
     * @param Token[] $tokens The array of `Token` objects from the `Lexer`.
     * @param string $pattern The original pattern string, stored for context and potential error reporting.
     */
    public function __construct(private readonly array $tokens, private readonly string $pattern)
    {
        // Pre-fill buffer with first token
        $this->fillBuffer(1);
    }

    /**
     * Retrieves the token at the current position of the stream.
     *
     * Purpose: This is the primary method for accessing the token that the parser
     * needs to evaluate now. It does not advance the stream pointer. Calling it
     * multiple times in a row will return the same token.
     *
     * @return Token The token at the current cursor position.
     *
     * @throws \RuntimeException If the stream has been fully consumed.
     */
    public function current(): Token
    {
        if (!isset($this->buffer[0])) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        return $this->buffer[0];
    }

    /**
     * Consumes the current token and advances the stream to the next one.
     *
     * Purpose: This method moves the stream's cursor forward by one position. It's the
     * core action of "consuming" a token after the parser has processed it. The consumed
     * token is temporarily stored in a history buffer to enable the `rewind()` feature.
     *
     * @return void
     *
     * @throws \RuntimeException If the stream is already at the end.
     */
    public function next(): void
    {
        if (empty($this->buffer)) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        $consumed = array_shift($this->buffer);
        $this->history[] = $consumed;
        $this->position++;
        $this->fillBuffer(1);

        // Keep history limited to avoid memory issues
        if (\count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    /**
     * Moves the stream cursor back by a specified number of positions.
     *
     * Purpose: This method allows the parser to "un-consume" tokens. It's essential for
     * implementing backtracking in the recursive descent parser. When a parsing function
     * speculatively consumes tokens but then fails to find a valid grammar rule, it can
     * call `rewind()` to restore the stream to its previous state before trying an
     * alternative parsing path.
     *
     * @param int $count The number of tokens to rewind. Must be a positive integer.
     *
     * @return void
     *
     * @throws \RuntimeException If trying to rewind more tokens than are available in the history buffer.
     */
    public function rewind(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        if ($count > \count($this->history)) {
            throw new \RuntimeException(\sprintf(
                'Cannot rewind %d tokens, only %d in history',
                $count,
                \count($this->history),
            ));
        }

        for ($i = 0; $i < $count; $i++) {
            $token = array_pop($this->history);
            if (null !== $token) {
                array_unshift($this->buffer, $token);
                $this->position--;
            }
        }
    }

    /**
     * Jumps the stream to an absolute token position.
     *
     * Purpose: This provides a more direct way to control the stream's cursor, often
     * used for "save/restore" patterns in the parser. A parser might get the current
     * position, attempt a complex parsing path, and if it fails, restore the exact
     * original position to try another path. It's a more robust alternative to
     * manually tracking how much to `rewind()`.
     *
     * @param int $position The absolute token index to move to.
     *
     * @return void
     */
    public function setPosition(int $position): void
    {
        $diff = $this->position - $position;
        if ($diff > 0) {
            $this->rewind($diff);
        } elseif ($diff < 0) {
            // Move forward
            for ($i = 0; $i < -$diff; $i++) {
                $this->next();
            }
        }
    }

    /**
     * Looks at a token ahead of or behind the current position without moving the cursor.
     *
     * Purpose: This is the essential "lookahead" (or "lookbehind") function for the parser.
     * It allows a parsing function to check the type of upcoming (or previous) tokens to
     * decide which grammar rule to apply, without consuming the tokens. For example, it can
     * check if a `(` is followed by `?` to distinguish a capturing group from a modified one.
     *
     * @param int $offset The relative position to look at. `1` means the next token, `2` is the
     *                    one after that, and `-1` is the previously consumed token.
     *
     * @return Token The token at the specified offset. If the offset is out of bounds (beyond
     *               the end of the stream or before the beginning of the history), a special
     *               `T_EOF` token is returned.
     */
    public function peek(int $offset = 1): Token
    {
        if ($offset < 0) {
            // Look back into history
            $historyIndex = \count($this->history) + $offset;
            if ($historyIndex >= 0 && isset($this->history[$historyIndex])) {
                return $this->history[$historyIndex];
            }

            return new Token(TokenType::T_EOF, '', 0);
        }

        $this->fillBuffer($offset + 1);

        return $this->buffer[$offset] ?? new Token(TokenType::T_EOF, '', $this->position + $offset);
    }

    /**
     * Verifies if the stream has any tokens left to be consumed.
     *
     * Purpose: This provides a safe way for the parser to check if it has reached the
     * end of the input. It's typically used in loops to ensure the parser doesn't try
     * to read past the final `T_EOF` token.
     *
     * @return bool True if there are more tokens available in the buffer, false otherwise.
     */
    public function hasMore(): bool
    {
        return !empty($this->buffer);
    }

    /**
     * Retrieves the current absolute position (index) of the stream's cursor.
     *
     * Purpose: This is used in conjunction with `setPosition()` to save and restore the
     * parser's state. A parsing function can store the result of `getPosition()` before
     * attempting a speculative parse, and then use `setPosition()` to return to that
     * exact spot if the speculation fails.
     *
     * @return int The zero-based index of the current token in the stream.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Retrieves the original, raw pattern string that this stream is based on.
     *
     * Purpose: While the stream primarily deals with tokens, having access to the
     * original string is useful for error reporting and context. It allows error
     * messages to include snippets of the original pattern, making debugging easier.
     *
     * @return string The original regex pattern.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Retrieves the complete, original array of all tokens.
     *
     * Purpose: This method is primarily for debugging and testing. It provides direct
     * access to the underlying token array, allowing a developer to inspect the entire
     * output of the `Lexer` at once. In normal operation, the parser should consume
     * tokens via `current()` and `next()`.
     *
     * @return Token[] The full array of tokens.
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Fill the buffer with tokens from the generator.
     */
    private function fillBuffer(int $minSize): void
    {
        if ($this->exhausted || \count($this->buffer) >= $minSize) {
            return;
        }

        while (\count($this->buffer) < $minSize) {
            $nextIndex = $this->position + \count($this->buffer);
            if (isset($this->tokens[$nextIndex])) {
                $this->buffer[] = $this->tokens[$nextIndex];
            } else {
                $this->exhausted = true;

                break;
            }
        }
    }
}
