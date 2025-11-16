<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Builder;

use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\RangeNode;

/**
 * A specialized builder for constructing character classes [].
 * This is used internally by RegexBuilder.
 */
class CharClassBuilder
{
    /** @var NodeInterface[] */
    private array $parts = [];

    /**
     * Adds a literal character or string to the class.
     */
    public function literal(string $value): self
    {
        foreach (mb_str_split($value) as $char) {
            $this->parts[] = new LiteralNode($char, 0, 0);
        }

        return $this;
    }

    /**
     * Adds a character range (e.g., "a-z").
     */
    public function range(string $start, string $end): self
    {
        if (1 !== mb_strlen($start) || 1 !== mb_strlen($end)) {
            throw new \InvalidArgumentException('Range parts must be single characters.');
        }
        $this->parts[] = new RangeNode(new LiteralNode($start, 0, 0), new LiteralNode($end, 0, 0), 0, 0);

        return $this;
    }

    public function digit(): self
    {
        $this->parts[] = new CharTypeNode('d', 0, 0);

        return $this;
    }

    public function notDigit(): self
    {
        $this->parts[] = new CharTypeNode('D', 0, 0);

        return $this;
    }

    public function whitespace(): self
    {
        $this->parts[] = new CharTypeNode('s', 0, 0);

        return $this;
    }

    public function notWhitespace(): self
    {
        $this->parts[] = new CharTypeNode('S', 0, 0);

        return $this;
    }

    public function word(): self
    {
        $this->parts[] = new CharTypeNode('w', 0, 0);

        return $this;
    }

    public function notWord(): self
    {
        $this->parts[] = new CharTypeNode('W', 0, 0);

        return $this;
    }

    public function posix(string $class): self
    {
        // Note: The Validator will check if the class name is valid
        $this->parts[] = new PosixClassNode($class, 0, 0);

        return $this;
    }

    /**
     * @return NodeInterface[]
     */
    public function build(): array
    {
        return $this->parts;
    }
}
