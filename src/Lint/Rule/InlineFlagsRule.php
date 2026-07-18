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
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\NodeInterface;

/**
 * Detects redundant inline flags and inline flags that override a global
 * modifier.
 *
 * The two rule IDs interleave per flag; keeping them in one rule preserves
 * the historical emission order.
 */
final class InlineFlagsRule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.flag.redundant', 'regex.lint.flag.override'];
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

        if (GroupType::T_GROUP_INLINE_FLAGS !== $node->type || null === $node->flags) {
            return [];
        }

        $flags = (string) $node->flags;
        if ('' === $flags) {
            return [];
        }

        $resetAll = str_starts_with($flags, '^');
        if ($resetAll) {
            $flags = substr($flags, 1);
        }

        [$set, $unset] = str_contains($flags, '-')
            ? explode('-', $flags, 2)
            : [$flags, ''];

        $baseFlags = $resetAll ? '' : $context->activeFlags();
        $issues = [];

        foreach (str_split($set) as $flag) {
            if ('' === $flag) {
                continue;
            }
            if (str_contains($baseFlags, $flag)) {
                $issues[] = new LintIssue(
                    'regex.lint.flag.redundant',
                    \sprintf("Inline flag '%s' is redundant; it is already set globally.", $flag),
                    $node->startPosition,
                );
            }
        }

        foreach (str_split($unset) as $flag) {
            if ('' === $flag) {
                continue;
            }

            if (!str_contains($baseFlags, $flag)) {
                $issues[] = new LintIssue(
                    'regex.lint.flag.redundant',
                    \sprintf("Inline flag '-%s' is redundant; the flag is not set globally.", $flag),
                    $node->startPosition,
                    \sprintf("Remove '-%s' from the inline flag group; it has no effect unless the flag is enabled globally.", $flag),
                );
            } else {
                $issues[] = new LintIssue(
                    'regex.lint.flag.override',
                    \sprintf("Inline flag '-%s' overrides a global modifier.", $flag),
                    $node->startPosition,
                    'Consider removing the global flag or limiting it to specific groups.',
                );
            }
        }

        return $issues;
    }
}
