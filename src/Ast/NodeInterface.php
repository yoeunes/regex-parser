<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

interface NodeInterface
{
    public function accept(VisitorInterface $visitor): mixed;
}
