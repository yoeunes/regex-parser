<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Lint\Rule;

use RegexParser\LintIssue;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * Detects non-capturing groups that wrap a single atom and can be removed.
 */
final class RedundantGroupRule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.group.redundant'];
    }

    public function getNodeTypes(): array
    {
        return [GroupNode::class];
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if (!$node instanceof GroupNode) {
            return [];
        }

        if (GroupType::T_GROUP_NON_CAPTURING !== $node->type || !$this->isRedundantGroup($node->child)) {
            return [];
        }

        return [new LintIssue(
            'regex.lint.group.redundant',
            'Redundant non-capturing group; it can be removed without changing behavior.',
            $node->startPosition,
        )];
    }

    private function isRedundantGroup(NodeInterface $node): bool
    {
        if ($node instanceof SequenceNode) {
            if (1 !== \count($node->children)) {
                return false;
            }

            return $this->isRedundantGroup($node->children[0]);
        }

        if ($node instanceof AlternationNode || $node instanceof QuantifierNode) {
            return false;
        }

        return $node instanceof LiteralNode
            || $node instanceof CharTypeNode
            || $node instanceof CharClassNode
            || $node instanceof CharLiteralNode
            || $node instanceof UnicodeNode
            || $node instanceof DotNode
            || $node instanceof AnchorNode
            || $node instanceof AssertionNode
            || $node instanceof KeepNode
            || $node instanceof UnicodePropNode
            || $node instanceof PosixClassNode
            || $node instanceof ControlCharNode
            || $node instanceof CommentNode
            || $node instanceof CalloutNode
            || $node instanceof ScriptRunNode;
    }
}
