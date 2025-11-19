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

namespace RegexParser\Builder;

use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\RangeNode;

/**
 * A helper to build character class definitions (content inside [...]).
 * Immutable builder pattern.
 */
final readonly class CharClass
{
    /**
     * @param array<NodeInterface> $parts
     */
    private function __construct(
        private array $parts = [],
        private bool $negated = false
    ) {}

    public static function create(): self
    {
        return new self();
    }

    public static function any(): self
    {
        return self::create(); // Empty logic handled elsewhere or implicitly via usage
    }

    public static function digit(): self
    {
        return self::create()->add(new CharTypeNode('d', 0, 0));
    }

    public static function word(): self
    {
        return self::create()->add(new CharTypeNode('w', 0, 0));
    }

    public static function whitespace(): self
    {
        return self::create()->add(new CharTypeNode('s', 0, 0));
    }

    public static function range(string $from, string $to): self
    {
        return self::create()->add(new RangeNode(
            new LiteralNode($from, 0, 0),
            new LiteralNode($to, 0, 0),
            0,
            0,
        ));
    }

    public static function literal(string ...$chars): self
    {
        $instance = self::create();
        foreach ($chars as $charString) {
            foreach (mb_str_split($charString) as $char) {
                $instance = $instance->add(new LiteralNode($char, 0, 0));
            }
        }

        return $instance;
    }

    public static function posix(string $class): self
    {
        return self::create()->add(new PosixClassNode($class, 0, 0));
    }

    public function union(self $other): self
    {
        return new self(array_merge($this->parts, $other->parts), $this->negated);
    }

    public function negate(): self
    {
        return new self($this->parts, true);
    }

    /**
     * Internal method to append a node and return new instance.
     */
    public function add(NodeInterface $node): self
    {
        $newParts = $this->parts;
        $newParts[] = $node;

        return new self($newParts, $this->negated);
    }

    public function buildNode(): CharClassNode
    {
        return new CharClassNode($this->parts, $this->negated, 0, 0);
    }
}
