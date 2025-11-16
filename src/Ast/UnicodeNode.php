<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class UnicodeNode implements NodeInterface
{
    public function __construct(public readonly string $code)
    {
    }

    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitUnicode($this);
    }
}
