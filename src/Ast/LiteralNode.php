<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * @template TReturn
 *
 * @implements NodeInterface<TReturn>
 */
class LiteralNode implements NodeInterface
{
    public function __construct(public readonly string $value)
    {
    }

    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitLiteral($this);
    }
}
