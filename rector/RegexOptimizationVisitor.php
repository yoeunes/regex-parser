<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Rector;

use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

/**
 * This visitor is used by RegexOptimizationRector.
 */
final class RegexOptimizationVisitor extends CompilerNodeVisitor
{
    public string $flags = '';

    public function visitCharClass(CharClassNode $node): string
    {
        // Optimization 1: [a-zA-Z0-9_] → \w (if no /u flag)
        if (!str_contains($this->flags, 'u')) {
            if ($this->isFullWordClass($node)) {
                return '\w';
            }
        }

        // Optimization 2: [0-9] → \d (safe with or without /u)
        if ($this->isDigitClass($node)) {
            return '\d';
        }

        return parent::visitCharClass($node);
    }

    private function isFullWordClass(CharClassNode $node): bool
    {
        if ($node->isNegated || 4 !== \count($node->parts)) {
            return false;
        }
        $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
        foreach ($node->parts as $part) {
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                $range = $part->start->value.'-'.$part->end->value;
                if (isset($partsFound[$range])) {
                    $partsFound[$range] = true;
                }
            } elseif ($part instanceof LiteralNode && '_' === $part->value) {
                $partsFound['_'] = true;
            }
        }

        return !\in_array(false, $partsFound, true);
    }

    private function isDigitClass(CharClassNode $node): bool
    {
        if ($node->isNegated || 1 !== \count($node->parts)) {
            return false;
        }

        $part = $node->parts[0];
        if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
            return '0' === $part->start->value && '9' === $part->end->value;
        }

        return false;
    }
}
