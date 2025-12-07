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

namespace RegexParser\NodeVisitor;

use RegexParser\Node;

/**
 * A visitor that generates test cases (matching and non-matching strings) for a regex pattern.
 *
 * Purpose: This visitor traverses the Abstract Syntax Tree (AST) of a regular expression
 * and generates sample strings that should match the pattern, as well as strings that should not.
 * This is invaluable for testing regex implementations, validating patterns, and ensuring
 * correctness in applications like Laravel or Symfony form validations.
 *
 * @extends AbstractNodeVisitor<array{matching: array<string>, non_matching: array<string>}>
 */
final class TestCaseGeneratorNodeVisitor extends AbstractNodeVisitor
{
    private const int MAX_SAMPLES = 3;

    /**
     * Visits a RegexNode and generates test cases for its pattern.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): array
    {
        return $node->pattern->accept($this);
    }

    /**
     * Visits an AlternationNode and generates test cases from one of its alternatives.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): array
    {
        // Choose the first alternative for simplicity
        $cases = $node->alternatives[0]->accept($this);

        // Add non-matching by choosing a different alternative if available
        $nonMatching = $cases['non_matching'];
        if (\count($node->alternatives) > 1) {
            $other = $node->alternatives[1]->accept($this)['matching'];
            $nonMatching = array_merge($nonMatching, \array_slice($other, 0, 1));
        }

        return [
            'matching' => \array_slice($cases['matching'], 0, self::MAX_SAMPLES),
            'non_matching' => \array_slice($nonMatching, 0, self::MAX_SAMPLES),
        ];
    }

    /**
     * Visits a SequenceNode and concatenates test cases from its children.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): array
    {
        $matching = [''];
        $nonMatching = [''];

        foreach ($node->children as $child) {
            $childCases = $child->accept($this);
            $newMatching = [];
            $newNonMatching = [];

            foreach ($matching as $m) {
                foreach ($childCases['matching'] as $cm) {
                    $newMatching[] = $m.$cm;
                }
            }

            foreach ($nonMatching as $nm) {
                foreach ($childCases['non_matching'] as $cnm) {
                    $newNonMatching[] = $nm.$cnm;
                }
            }

            $matching = \array_slice($newMatching, 0, self::MAX_SAMPLES);
            $nonMatching = \array_slice($newNonMatching, 0, self::MAX_SAMPLES);
        }

        return [
            'matching' => $matching,
            'non_matching' => $nonMatching,
        ];
    }

    /**
     * Visits a GroupNode and generates test cases from its child.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a grouping construct
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): array
    {
        return $node->child->accept($this);
    }

    /**
     * Visits a QuantifierNode and generates test cases based on repetition.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): array
    {
        $childCases = $node->node->accept($this);
        $range = $this->parseQuantifierRange($node->quantifier);
        $min = $range[0];
        $max = $range[1];

        $matching = [];
        for ($i = $min; $i <= min($max ?? $min + 2, $min + 2); $i++) {
            $sample = str_repeat($childCases['matching'][0] ?? '', $i);
            $matching[] = $sample;
        }

        $nonMatching = [];
        if ($min > 0) {
            // Too few
            $nonMatching[] = str_repeat($childCases['matching'][0] ?? '', $min - 1);
        }
        if (null !== $max) {
            // Too many
            $nonMatching[] = str_repeat($childCases['matching'][0] ?? '', $max + 1);
        }

        return [
            'matching' => \array_slice($matching, 0, self::MAX_SAMPLES),
            'non_matching' => \array_slice($nonMatching, 0, self::MAX_SAMPLES),
        ];
    }

    /**
     * Visits a LiteralNode and generates test cases.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): array
    {
        $value = $node->value;
        $matching = [$value];
        $nonMatching = [];

        // Generate non-matching by changing one character
        if ('' !== $value) {
            $chars = str_split($value);
            for ($i = 0; $i < min(\strlen($value), 2); $i++) {
                $modified = $chars;
                $modified[$i] = \chr(\ord($chars[$i]) + 1);
                $nonMatching[] = implode('', $modified);
            }
        }

        return [
            'matching' => $matching,
            'non_matching' => \array_slice($nonMatching, 0, self::MAX_SAMPLES),
        ];
    }

    /**
     * Visits a CharTypeNode and generates test cases.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a character type
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): array
    {
        $sample = $this->generateForCharType($node->value);
        $matching = [$sample];
        $nonMatching = ['!']; // Simple non-matching

        return [
            'matching' => $matching,
            'non_matching' => $nonMatching,
        ];
    }

    /**
     * Visits a DotNode and generates test cases.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): array
    {
        return [
            'matching' => ['a'],
            'non_matching' => ["\n"], // Assuming dot doesn't match newline
        ];
    }

    /**
     * Visits an AnchorNode and returns empty test cases (anchors don't consume).
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return array{matching: array<string>, non_matching: array<string>} empty test cases
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    /**
     * Visits an AssertionNode and returns empty test cases.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return array{matching: array<string>, non_matching: array<string>} empty test cases
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    /**
     * Visits a CharClassNode and generates test cases.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): array
    {
        if (empty($node->parts)) {
            return [
                'matching' => [],
                'non_matching' => ['a'],
            ];
        }

        $sample = $node->parts[0]->accept($this)['matching'][0] ?? 'a';
        $matching = [$sample];
        $nonMatching = $node->isNegated ? [] : ['!'];

        return [
            'matching' => $matching,
            'non_matching' => $nonMatching,
        ];
    }

    /**
     * Visits a RangeNode and generates test cases.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return array{matching: array<string>, non_matching: array<string>} test cases
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): array
    {
        if (!$node->start instanceof Node\LiteralNode || !$node->end instanceof Node\LiteralNode) {
            return [
                'matching' => ['a'],
                'non_matching' => ['!'],
            ];
        }

        $start = $node->start->value;
        $end = $node->end->value;
        $sample = $start;
        $nonMatching = \chr(\ord($end) + 1);

        return [
            'matching' => [$sample],
            'non_matching' => [$nonMatching],
        ];
    }

    // Other nodes return basic cases
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => ['x'],
        ];
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): array
    {
        return [
            'matching' => ['a'],
            'non_matching' => ['!'],
        ];
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): array
    {
        return [
            'matching' => ['a'],
            'non_matching' => ['1'],
        ];
    }

    #[\Override]
    public function visitOctal(Node\OctalNode $node): array
    {
        return [
            'matching' => ['A'],
            'non_matching' => ['!'],
        ];
    }

    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): array
    {
        return [
            'matching' => ['A'],
            'non_matching' => ['!'],
        ];
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): array
    {
        return [
            'matching' => ['a'],
            'non_matching' => ['1'],
        ];
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): array
    {
        return $node->yes->accept($this);
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => ['x'],
        ];
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    #[\Override]
    public function visitDefine(Node\DefineNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    #[\Override]
    public function visitKeep(Node\KeepNode $node): array
    {
        return [
            'matching' => [''],
            'non_matching' => [''],
        ];
    }

    /**
     * Parses a quantifier string into min and max.
     *
     * @param string $q the quantifier
     *
     * @return array{0: int, 1: int|null} min and max
     */
    private function parseQuantifierRange(string $q): array
    {
        return match ($q) {
            '*' => [0, null],
            '+' => [1, null],
            '?' => [0, 1],
            default => preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    [(int) $m[1], null] :
                    [(int) $m[1], (int) $m[2]]
                ) :
                    [(int) $m[1], (int) $m[1]]
                ) :
                [1, 1],
        };
    }

    /**
     * Generates a sample character for a character type.
     *
     * @param string $type the type
     *
     * @return string the sample
     */
    private function generateForCharType(string $type): string
    {
        return match ($type) {
            'd' => '0',
            'D' => 'a',
            's' => ' ',
            'S' => 'a',
            'w' => 'a',
            'W' => '!',
            'h' => ' ',
            'H' => 'a',
            'v' => "\n",
            'V' => 'a',
            'R' => "\r\n",
            default => 'a',
        };
    }
}
