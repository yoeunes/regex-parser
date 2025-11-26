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

namespace RegexParser\Stream;

use RegexParser\Token;
use RegexParser\TokenType;

/**
 * Token stream wrapper around a generator.
 * Provides lookahead capabilities while maintaining memory efficiency.
 * Uses a limited buffer for peeking ahead without loading entire token list.
 */
final class TokenStream
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
     * @param \Generator<int, Token> $generator
     */
    public function __construct(private readonly \Generator $generator)
    {
        // Pre-fill buffer with first token
        $this->fillBuffer(1);
    }

    /**
     * Get the current token without advancing.
     */
    public function current(): Token
    {
        if (!isset($this->buffer[0])) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        return $this->buffer[0];
    }

    /**
     * Advance to the next token.
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
     * Rewind the stream by the specified number of tokens.
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
     * Set position to an absolute position (for save/restore patterns).
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
     * Peek at a token relative to current position without advancing.
     * Supports negative offsets to look back at previous tokens.
     * Returns EOF token if beyond the end of stream or before history.
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
     * Check if stream has more tokens.
     */
    public function hasMore(): bool
    {
        return !empty($this->buffer);
    }

    /**
     * Get current position in stream.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Fill the buffer with tokens from the generator.
     */
    private function fillBuffer(int $minSize): void
    {
        if ($this->exhausted || \count($this->buffer) >= $minSize) {
            return;
        }

        while (\count($this->buffer) < $minSize && $this->generator->valid()) {
            $this->buffer[] = $this->generator->current();
            $this->generator->next();
        }

        if (!$this->generator->valid()) {
            $this->exhausted = true;
        }
    }
}
