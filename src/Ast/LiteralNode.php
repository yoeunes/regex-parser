<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a literal character (e.g., "a", "1").
 */
class LiteralNode implements NodeInterface
{
    public function __construct(public readonly string $value)
    {
    }

    /**
     * @template T
     *
     * @param VisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitLiteral($this);
    }
}
