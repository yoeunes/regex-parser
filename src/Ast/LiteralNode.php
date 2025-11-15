<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class LiteralNode implements NodeInterface
{
    public function __construct(public readonly string $value)
    {
    }

    public function accept(VisitorInterface $visitor): mixed
    {
        return $visitor->visitLiteral($this);
    }
}
