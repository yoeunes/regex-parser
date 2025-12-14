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
 * Consumable stream of tokens with lookahead and rewind capabilities.
 */
final class TokenStream
{
    /**
     * @var array<int, Token>
     */
    private array $buffer = [];

    /**
     * @var array<int, Token>
     */
    private array $history = [];

    private int $position = 0;

    private bool $exhausted = false;

    /**
     * @param array<Token> $tokens
     */
    public function __construct(private readonly array $tokens, private readonly string $pattern)
    {
        // Pre-fill buffer with first token
        $this->fillBuffer(1);
    }

    /**
     * @throws \RuntimeException
     */
    public function current(): Token
    {
        if (!isset($this->buffer[0])) {
            throw new \RuntimeException('Token stream is exhausted');
        }

        return $this->buffer[0];
    }

    /**
     * @throws \RuntimeException
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

        if (\count($this->history) > 100) {
            array_shift($this->history);
        }
    }

    /**
     * @throws \RuntimeException
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

    public function hasMore(): bool
    {
        return !empty($this->buffer);
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
