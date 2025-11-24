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
     * @var \Generator<int, Token>
     */
    private \Generator $generator;

    /**
     * Buffer for lookahead tokens.
     *
     * @var array<int, Token>
     */
    private array $buffer = [];

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
    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
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

        array_shift($this->buffer);
        $this->position++;
        $this->fillBuffer(1);
    }

    /**
     * Peek ahead at a token without advancing.
     * Returns EOF token if beyond the end of stream.
     */
    public function peek(int $offset = 1): Token
    {
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
