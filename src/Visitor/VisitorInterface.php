<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;

interface VisitorInterface
{
    public function visitAlternation(AlternationNode $node): mixed;

    public function visitGroup(GroupNode $node): mixed;

    public function visitLiteral(LiteralNode $node): mixed;

    public function visitQuantifier(QuantifierNode $node): mixed;
}
