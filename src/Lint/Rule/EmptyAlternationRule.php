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

use RegexParser\Lint\Rule\Support\NodePredicates;
use RegexParser\LintIssue;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\NodeInterface;

/**
 * Detects empty alternatives in alternations.
 */
final class EmptyAlternationRule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.alternation.empty'];
    }

    public function getNodeTypes(): array
    {
        return [AlternationNode::class];
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if (!$node instanceof AlternationNode) {
            return [];
        }

        $issues = [];
        foreach ($node->alternatives as $alt) {
            if (NodePredicates::isSyntacticallyEmptyAlternative($alt)) {
                $issues[] = new LintIssue(
                    'regex.lint.alternation.empty',
                    'Alternation contains an empty alternative.',
                    $alt->getStartPosition(),
                    'Use a quantifier (e.g., "?") if the empty string should be allowed.',
                );
            }
        }

        return $issues;
    }
}
