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
use RegexParser\Node\AlternationNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * Detects a useless 'i' flag: the pattern contains no case-sensitive
 * characters and no backreferences.
 *
 * Stateful: aggregates case-sensitivity facts during traversal and emits
 * in finish().
 */
final class UselessIFlagRule extends AbstractLintRule
{
    private bool $hasCaseSensitiveChars = false;

    private bool $hasBackreferences = false;

    private bool $trackCaseSensitivity = false;

    private bool $unicodeMode = false;

    private bool $intlAvailable = false;

    public function getRuleIds(): array
    {
        return ['regex.lint.flag.useless.i'];
    }

    public function getNodeTypes(): array
    {
        return [
            LiteralNode::class,
            CharClassNode::class,
            UnicodeNode::class,
            CharLiteralNode::class,
            UnicodePropNode::class,
            BackrefNode::class,
        ];
    }

    public function begin(LintContext $context): void
    {
        $this->hasCaseSensitiveChars = false;
        $this->hasBackreferences = false;
        $this->trackCaseSensitivity = $context->pattern->hasFlag('i');
        $this->unicodeMode = $context->pattern->unicodeMode;
        $this->intlAvailable = $context->pattern->intlAvailable;
    }

    public function check(NodeInterface $node, LintContext $context): array
    {
        if ($node instanceof BackrefNode) {
            $this->hasBackreferences = true;

            return [];
        }

        if (!$this->trackCaseSensitivity || $this->hasCaseSensitiveChars) {
            return [];
        }

        if ($node instanceof LiteralNode) {
            if ($this->stringHasCaseSensitiveLetters($node->value)) {
                $this->hasCaseSensitiveChars = true;
            }

            return [];
        }

        if ($node instanceof CharClassNode) {
            $expression = $node->expression;
            if ($expression instanceof AlternationNode) {
                foreach ($expression->alternatives as $alt) {
                    if ($this->charClassPartHasLetters($alt)) {
                        $this->hasCaseSensitiveChars = true;

                        break;
                    }
                }
            } elseif ($this->charClassPartHasLetters($expression)) {
                $this->hasCaseSensitiveChars = true;
            }

            return [];
        }

        if ($node instanceof UnicodeNode) {
            $code = CodePoints::parseUnicodeEscape($node->code);
            if (null !== $code && $this->codePointHasCase($code)) {
                $this->hasCaseSensitiveChars = true;
            }

            return [];
        }

        if ($node instanceof CharLiteralNode) {
            if ($this->charLiteralHasCaseSensitiveLetter($node)) {
                $this->hasCaseSensitiveChars = true;
            }

            return [];
        }

        if ($node instanceof UnicodePropNode) {
            if ($this->unicodePropIsCaseSensitive($node->prop)) {
                $this->hasCaseSensitiveChars = true;
            }
        }

        return [];
    }

    public function finish(LintContext $context): array
    {
        if ($context->pattern->hasFlag('i') && !$this->hasCaseSensitiveChars && !$this->hasBackreferences) {
            return [new LintIssue(
                'regex.lint.flag.useless.i',
                "Flag 'i' is useless: the pattern contains no case-sensitive characters.",
            )];
        }

        return [];
    }

    private function charClassPartHasLetters(NodeInterface $node): bool
    {
        if ($node instanceof LiteralNode) {
            return $this->stringHasCaseSensitiveLetters($node->value);
        }

        if ($node instanceof CharLiteralNode) {
            return $this->charLiteralHasCaseSensitiveLetter($node);
        }

        if ($node instanceof UnicodeNode) {
            $codePoint = CodePoints::parseUnicodeEscape($node->code);

            return null !== $codePoint && $this->codePointHasCase($codePoint);
        }

        if ($node instanceof UnicodePropNode) {
            return $this->unicodePropIsCaseSensitive($node->prop);
        }

        if ($node instanceof PosixClassNode) {
            return $this->posixClassHasCaseSensitiveLetters($node->class);
        }

        if ($node instanceof RangeNode) {
            return $this->rangeHasLetters($node);
        }

        // Other types like CharTypeNode are case-insensitive by design.
        return false;
    }

    private function rangeHasLetters(RangeNode $node): bool
    {
        $start = CodePoints::fromNode($node->start, $this->unicodeMode, $this->intlAvailable);
        $end = CodePoints::fromNode($node->end, $this->unicodeMode, $this->intlAvailable);

        if (null === $start || null === $end) {
            return false;
        }

        $min = min($start, $end);
        $max = max($start, $end);

        if ($this->rangeHasAsciiLetters($min, $max)) {
            return true;
        }

        if (!$this->unicodeMode || !$this->intlAvailable) {
            return false;
        }

        return $this->codePointHasCase($start) || $this->codePointHasCase($end);
    }

    private function rangeHasAsciiLetters(int $min, int $max): bool
    {
        return ($min <= \ord('Z') && $max >= \ord('A'))
            || ($min <= \ord('z') && $max >= \ord('a'));
    }

    private function stringHasCaseSensitiveLetters(string $value): bool
    {
        if ('' === $value) {
            return false;
        }

        if (preg_match('/[A-Za-z]/', $value) > 0) {
            return true;
        }

        if (!$this->unicodeMode || !$this->intlAvailable) {
            return false;
        }

        $chars = preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chars) {
            return false;
        }

        foreach ($chars as $char) {
            $codePoint = \IntlChar::ord($char);
            if ($this->codePointHasCase($codePoint)) {
                return true;
            }
        }

        return false;
    }

    private function charLiteralHasCaseSensitiveLetter(CharLiteralNode $node): bool
    {
        $codePoint = $node->codePoint;
        if ($codePoint < 0) {
            $codePoint = CodePoints::parseUnicodeEscape($node->originalRepresentation) ?? $codePoint;
        }

        if ($codePoint >= 0) {
            return $this->codePointHasCase($codePoint);
        }

        if (1 === \strlen($node->originalRepresentation)) {
            return $this->stringHasCaseSensitiveLetters($node->originalRepresentation);
        }

        return false;
    }

    private function codePointHasCase(int $codePoint): bool
    {
        if ($codePoint < 0 || $codePoint > 0x10FFFF) {
            return false;
        }

        if (!$this->intlAvailable) {
            return ($codePoint >= \ord('A') && $codePoint <= \ord('Z'))
                || ($codePoint >= \ord('a') && $codePoint <= \ord('z'));
        }

        if (!\IntlChar::isalpha($codePoint)) {
            return false;
        }

        return \IntlChar::toupper($codePoint) !== $codePoint
            || \IntlChar::tolower($codePoint) !== $codePoint;
    }

    private function unicodePropIsCaseSensitive(string $prop): bool
    {
        if ('' === $prop) {
            return false;
        }

        $normalized = $this->normalizeUnicodePropName($prop);

        return \in_array($normalized, [
            'lu',
            'll',
            'lt',
            'lc',
            'l&',
            'upper',
            'lower',
            'title',
            'uppercase_letter',
            'lowercase_letter',
            'titlecase_letter',
            'cased_letter',
        ], true);
    }

    private function normalizeUnicodePropName(string $prop): string
    {
        $normalized = ltrim($prop, '^');
        $normalized = trim($normalized, '{}');
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return strtolower($normalized);
    }

    private function posixClassHasCaseSensitiveLetters(string $class): bool
    {
        $normalized = strtolower(ltrim($class, '^'));

        return \in_array($normalized, ['upper', 'lower'], true);
    }
}
