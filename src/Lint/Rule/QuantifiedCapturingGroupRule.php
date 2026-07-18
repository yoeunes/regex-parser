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

use RegexParser\Lint\Rule\Support\QuantifierMath;
use RegexParser\LintIssue;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\QuantifierNode;
use RegexParser\Severity;

/**
 * Detects quantified capturing groups, where only the last iteration's
 * capture is retained.
 */
final class QuantifiedCapturingGroupRule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.group.quantifiedCapture'];
    }

    public function getNodeTypes(): array
    {
        return [QuantifierNode::class];
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if (!$node instanceof QuantifierNode) {
            return [];
        }

        $inner = $node->node;
        if (!$inner instanceof GroupNode) {
            return [];
        }

        $isCapturing = \in_array($inner->type, [
            GroupType::T_GROUP_CAPTURING,
            GroupType::T_GROUP_NAMED,
        ], true);

        if (!$isCapturing) {
            return [];
        }

        // Only flag repeating quantifiers (not ?, {1}, {0,1})
        if (!QuantifierMath::isRepeatable($node->quantifier)) {
            return [];
        }

        $isNamed = null !== $inner->name;
        $label = $isNamed
            ? \sprintf('named group "(?<%s>...)"', $inner->name)
            : 'capturing group "(...)"';

        return [new LintIssue(
            'regex.lint.group.quantifiedCapture',
            \sprintf(
                'Quantified %s with "%s": only the last iteration\'s capture is retained.',
                $label,
                $node->quantifier,
            ),
            $node->startPosition,
            'Use a non-capturing group (?:...) for the repetition and capture the whole match, or restructure the pattern.',
            $isNamed ? Severity::Warning : Severity::Info,
        )];
    }
}
