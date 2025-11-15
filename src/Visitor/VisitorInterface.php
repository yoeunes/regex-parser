<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;

/**
 * Defines the Visitor interface for traversing the AST.
 * Uses the Visitor design pattern.
 *
 * @template-covariant TReturn The return type of the visitor (e.g., 'string' for Compiler, 'void' for Validator)
 */
interface VisitorInterface
{
    /**
     * @return TReturn
     */
    public function visitRegex(RegexNode $node);

    /**
     * @return TReturn
     */
    public function visitAlternation(AlternationNode $node);

    /**
     * @return TReturn
     */
    public function visitSequence(SequenceNode $node);

    /**
     * @return TReturn
     */
    public function visitGroup(GroupNode $node);

    /**
     * @return TReturn
     */
    public function visitQuantifier(QuantifierNode $node);

    /**
     * @return TReturn
     */
    public function visitLiteral(LiteralNode $node);

    /**
     * @return TReturn
     */
    public function visitCharType(CharTypeNode $node);

    /**
     * @return TReturn
     */
    public function visitDot(DotNode $node);

    /**
     * @return TReturn
     */
    public function visitAnchor(AnchorNode $node);
}
