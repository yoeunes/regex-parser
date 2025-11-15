<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;

/**
 * @template TReturn Le type de retour du visiteur (ex: 'string' pour Compiler, 'void' pour Validator)
 */
interface VisitorInterface
{
    /**
     * @return TReturn
     */
    public function visitAlternation(AlternationNode $node);

    /**
     * @return TReturn
     */
    public function visitGroup(GroupNode $node);

    /**
     * @return TReturn
     */
    public function visitLiteral(LiteralNode $node);

    /**
     * @return TReturn
     */
    public function visitQuantifier(QuantifierNode $node);

    /**
     * @return TReturn
     */
    public function visitSequence(SequenceNode $node);
}
