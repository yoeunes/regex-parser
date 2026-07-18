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

use RegexParser\Lint\Rule\Support\CodePoints;
use RegexParser\LintIssue;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\UnicodeNode;

/**
 * Detects out-of-range Unicode and octal escapes and unknown Unicode
 * character names.
 */
final class SuspiciousEscapeRule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.escape.suspicious'];
    }

    public function getNodeTypes(): array
    {
        return [UnicodeNode::class, CharLiteralNode::class];
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if ($node instanceof UnicodeNode) {
            $code = CodePoints::parseUnicodeEscape($node->code);

            if (null !== $code && $code > 0x10FFFF) {
                return [new LintIssue(
                    'regex.lint.escape.suspicious',
                    \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->code),
                    $node->startPosition,
                )];
            }

            return [];
        }

        if (!$node instanceof CharLiteralNode) {
            return [];
        }

        if (CharLiteralType::UNICODE === $node->type && $node->codePoint > 0x10FFFF) {
            return [new LintIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            )];
        }

        if (\in_array($node->type, [CharLiteralType::OCTAL, CharLiteralType::OCTAL_LEGACY], true) && $node->codePoint > 0xFF) {
            return [new LintIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious octal escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            )];
        }

        if (CharLiteralType::UNICODE_NAMED === $node->type && class_exists(\IntlChar::class)) {
            $name = $node->originalRepresentation;
            if (preg_match('/^\\\\N\\{(.+)}$/', $name, $matches)) {
                $char = \IntlChar::charFromName($matches[1]);
                if (null === $char) {
                    return [new LintIssue(
                        'regex.lint.escape.suspicious',
                        \sprintf('Unknown Unicode character name "%s".', $matches[1]),
                        $node->startPosition,
                    )];
                }
            }
        }

        return [];
    }
}
