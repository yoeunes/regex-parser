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

namespace RegexParser\Internal;

use RegexParser\Exception\SyntaxErrorException;
use RegexParser\TokenStream;
use RegexParser\TokenType;

/**
 * Reads the name of a group, whichever way the pattern spells it.
 *
 * PCRE takes "(?<name>", "(?'name'", "(?P<name>" and "(?P=name", and Python
 * patterns bring double quotes along; the name itself is the same in all of
 * them. The reader also keeps the names already used, since a pattern may
 * only repeat one under the "J" modifier.
 *
 * @internal
 */
final class GroupNameReader
{
    /**
     * @var array<string, true>
     */
    private array $used = [];

    private bool $duplicatesAllowed = false;

    public function __construct(private readonly TokenStream $stream) {}

    /**
     * Whether the pattern currently allows two groups to share a name, which
     * the "J" modifier says — globally, or inside a "(?J:...)" group.
     */
    public function allowDuplicates(bool $allowed): void
    {
        $this->duplicatesAllowed = $allowed;
    }

    public function duplicatesAllowed(): bool
    {
        return $this->duplicatesAllowed;
    }

    public function forget(): void
    {
        $this->used = [];
    }

    /**
     * @param int|null $errorPosition where to point when the name is wrong,
     *                                for a caller that knows better than the
     *                                token under the cursor
     * @param bool     $register      false for a name that refers to a group
     *                                rather than declaring one
     *
     * @throws SyntaxErrorException
     */
    public function read(?int $errorPosition = null, bool $register = true): string
    {
        $nameStart = $errorPosition ?? $this->stream->current()->position;
        $quote = $this->openingQuote();
        $name = $this->readName($quote);

        if (null !== $quote) {
            $this->closeQuote($quote);
        }

        if ('' === $name) {
            throw $this->error(\sprintf('Expected group name at position %d', $nameStart), $nameStart);
        }

        // PCRE group names are word characters only and must not start with a digit.
        if (1 !== preg_match('/^[A-Za-z_]\w*+$/', $name)) {
            throw $this->error(
                \sprintf(
                    'Invalid group name "%s": names must contain only word characters and must not start with a digit.',
                    $name,
                ),
                $nameStart,
            );
        }

        if ($register) {
            $this->register($name, $nameStart);
        }

        return $name;
    }

    /**
     * @throws SyntaxErrorException
     */
    public function register(string $name, int $position): void
    {
        if (isset($this->used[$name]) && !$this->duplicatesAllowed) {
            throw $this->error(\sprintf('Duplicate group name "%s" at position %d.', $name, $position), $position);
        }

        $this->used[$name] = true;
    }

    private function openingQuote(): ?string
    {
        if (!$this->stream->checkLiteral("'") && !$this->stream->checkLiteral('"')) {
            return null;
        }

        $quote = $this->stream->current()->value;
        $this->stream->advance();

        return $quote;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function readName(?string $quote): string
    {
        $name = '';

        while (!$this->stream->checkLiteral('>') && !$this->stream->checkLiteral('}') && !$this->stream->isAtEnd()) {
            if (null !== $quote && $this->stream->checkLiteral($quote)) {
                break;
            }

            if ($this->stream->check(TokenType::T_GROUP_CLOSE)) {
                break;
            }

            if (!$this->stream->check(TokenType::T_LITERAL) && !$this->stream->check(TokenType::T_LITERAL_ESCAPED)) {
                throw $this->error(
                    \sprintf('Unexpected token "%s" in group name', $this->stream->current()->value),
                    $this->stream->current()->position,
                );
            }

            $name .= $this->stream->current()->value;
            $this->stream->advance();
        }

        return $name;
    }

    /**
     * @throws SyntaxErrorException
     */
    private function closeQuote(string $quote): void
    {
        if (!$this->stream->checkLiteral($quote)) {
            throw $this->error(
                \sprintf(
                    'Expected closing quote "%s" for group name at position %d',
                    $quote,
                    $this->stream->current()->position,
                ),
                $this->stream->current()->position,
            );
        }

        $this->stream->advance();
    }

    private function error(string $message, int $position): SyntaxErrorException
    {
        return SyntaxErrorException::withContext($message, $position, $this->stream->getPattern());
    }
}
