<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * @template TReturn
 */
interface NodeInterface
{
    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor);
}
