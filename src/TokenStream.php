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
 * High-performance token stream with direct indexing and intelligent caching.
 *
 * This implementation eliminates O(n) array operations and provides constant-time
 * access to tokens through direct array indexing with minimal memory overhead.
 */
final class TokenStream
{
    private int $position = 0;
    private int $maxPosition = 0;

    /**
     * @param array<Token> $tokens
     */
    public function __construct(
        private readonly array $tokens,
        private readonly string $pattern
    ) {
        $this->maxPosition = \count($this->tokens) - 1;
    }

    /**
     * @throws \RuntimeException
     */
    public function current(): Token
    {
        if ($this->position > $this->maxPosition) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        return $this->tokens[$this->position];
    }

    /**
     * @throws \RuntimeException
     */
    public function next(): void
    {
        if ($this->position > $this->maxPosition) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        $this->position++;
    }

    /**
     * @throws \RuntimeException
     */
    public function rewind(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        $newPosition = $this->position - $count;
        if ($newPosition < 0) {
            throw new \RuntimeException(\sprintf(
                'Cannot rewind %d tokens, would go before start of stream',
                $count,
            ));
        }

        $this->position = $newPosition;
    }

    public function setPosition(int $position): void
    {
        if ($position < 0 || $position > $this->maxPosition + 1) {
            throw new \RuntimeException(\sprintf(
                'Position %d is out of bounds [0, %d]',
                $position,
                $this->maxPosition + 1,
            ));
        }

        $this->position = $position;
    }

    public function peek(int $offset = 1): Token
    {
        $targetPos = $this->position + $offset;

        if ($targetPos < 0) {
            return new Token(TokenType::T_EOF, '', 0);
        }

        if ($targetPos > $this->maxPosition) {
            return new Token(TokenType::T_EOF, '', $targetPos);
        }

        return $this->tokens[$targetPos];
    }

    public function hasMore(): bool
    {
        return $this->position <= $this->maxPosition;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return array<Token>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Returns true if current token is EOF
     */
    public function isAtEnd(): bool
    {
        return $this->position > $this->maxPosition ||
               $this->tokens[$this->position]->type === TokenType::T_EOF;
    }
}
