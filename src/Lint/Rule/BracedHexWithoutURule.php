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
use RegexParser\Severity;

/**
 * Detects braced Unicode escapes (e.g. \x{100}) that require the /u flag
 * for code points above U+FF.
 */
final class BracedHexWithoutURule extends AbstractLintRule
{
    public function getRuleIds(): array
    {
        return ['regex.lint.unicode.bracedHexWithoutU'];
    }

    public function getNodeTypes(): array
    {
        return [UnicodeNode::class, CharLiteralNode::class];
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if ($context->pattern->unicodeMode) {
            return [];
        }

        if ($node instanceof UnicodeNode) {
            $code = CodePoints::parseUnicodeEscape($node->code);

            if (CodePoints::isBracedUnicodeEscape($node->code) && null !== $code && $code > 0xFF) {
                return [new LintIssue(
                    'regex.lint.unicode.bracedHexWithoutU',
                    \sprintf('Unicode escape "%s" requires /u flag for code points > U+FF.', $node->code),
                    $node->startPosition,
                    'Add /u flag or use byte-level encoding.',
                    Severity::Error,
                )];
            }

            return [];
        }

        if (!$node instanceof CharLiteralNode) {
            return [];
        }

        if (CharLiteralType::UNICODE === $node->type
            && CodePoints::isBracedUnicodeEscape($node->originalRepresentation)
            && $node->codePoint > 0xFF
        ) {
            return [new LintIssue(
                'regex.lint.unicode.bracedHexWithoutU',
                \sprintf('Unicode escape "%s" requires /u flag for code points > U+FF.', $node->originalRepresentation),
                $node->startPosition,
                'Add /u flag or use byte-level encoding.',
                Severity::Error,
            )];
        }

        return [];
    }
}
