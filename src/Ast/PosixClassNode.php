<?php
namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class PosixClassNode implements NodeInterface
{
    public function __construct(public readonly string $class)
    {
    }

    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitPosixClass($this);
    }
}
