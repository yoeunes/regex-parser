<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\NodeVisitor;

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * A visitor that generates a random sample string that matches the AST.
 *
 * @implements NodeVisitorInterface<string>
 */
class SampleGeneratorVisitor implements NodeVisitorInterface
{
    /**
     * @param int $maxRepetition max times to repeat for * or + quantifiers
     *                           to prevent excessively long or infinite samples
     */
    public function __construct(private readonly int $maxRepetition = 3)
    {
    }

    /**
     * Stores generated text from capturing groups.
     * Keyed by both numeric index and name (if available).
     *
     * @var array<int|string, string>
     */
    private array $captures = [];
    private int $groupCounter = 1;

    public function visitRegex(RegexNode $node): string
    {
        // Reset state for this run
        $this->captures = [];
        $this->groupCounter = 1;

        // Note: Flags (like /i) are ignored, as we generate the sample
        // from the literal pattern.
        return $node->pattern->accept($this);
    }

    public function visitAlternation(AlternationNode $node): string
    {
        if (empty($node->alternatives)) {
            return '';
        }

        // Pick one of the alternatives at random
        $randomKey = array_rand($node->alternatives);
        $chosenAlt = $node->alternatives[$randomKey];

        return $chosenAlt->accept($this);
    }

    public function visitSequence(SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);

        return implode('', $parts);
    }

    public function visitGroup(GroupNode $node): string
    {
        $result = $node->child->accept($this);

        // Store the result if it's a capturing group
        if (GroupType::T_GROUP_CAPTURING === $node->type) {
            $this->captures[$this->groupCounter++] = $result;
        } elseif (GroupType::T_GROUP_NAMED === $node->type) {
            $this->captures[$this->groupCounter++] = $result;
            if ($node->name) {
                $this->captures[$node->name] = $result;
            }
        }

        // For non-capturing, lookarounds, etc., just return the child's result
        return $result;
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        [$min, $max] = $this->parseQuantifierRange($node->quantifier);

        // Pick a random number of repetitions
        try {
            // $min and $max are guaranteed to be in the correct order
            // by parseQuantifierRange()
            $repeats = ($min === $max) ? $min : random_int($min, $max);
        } catch (\Throwable) {
            $repeats = $min; // Fallback
        }

        $parts = [];
        for ($i = 0; $i < $repeats; ++$i) {
            $parts[] = $node->node->accept($this);
        }

        return implode('', $parts);
    }

    /**
     * @return array{0: int, 1: int} [min, max]
     */
    private function parseQuantifierRange(string $q): array
    {
        $range = match ($q) {
            '*' => [0, $this->maxRepetition],
            '+' => [1, $this->maxRepetition],
            '?' => [0, 1],
            default => preg_match('/^{(\d+)(?:,(\d*))?}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    [(int) $m[1], (int) $m[1] + $this->maxRepetition] : // {n,}
                    [(int) $m[1], (int) $m[2]] // {n,m}
                ) :
                    [(int) $m[1], (int) $m[1]] // {n}
                ) :
                [0, 0], // Fallback
        };

        // Ensure min <= max, as Validator may not have run.
        // This handles invalid cases like {5,2} and silences PHPStan
        if ($range[1] < $range[0]) {
            $range[1] = $range[0];
        }

        return $range;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        return $node->value;
    }

    public function visitCharType(CharTypeNode $node): string
    {
        return $this->generateForCharType($node->value);
    }

    public function visitDot(DotNode $node): string
    {
        // Generate a random, simple, printable ASCII char
        return $this->getRandomChar(['a', 'b', 'c', '1', '2', '3', ' ']);
    }

    public function visitAnchor(AnchorNode $node): string
    {
        // Anchors do not generate text
        return '';
    }

    public function visitAssertion(AssertionNode $node): string
    {
        // Assertions do not generate text
        return '';
    }

    public function visitKeep(KeepNode $node): string
    {
        // \K does not generate text
        return '';
    }

    public function visitCharClass(CharClassNode $node): string
    {
        if ($node->isNegated) {
            // Generating for a negated class is complex.
            // We'd have to know the full set of all possible chars
            // (ASCII? Unicode?) and subtract the parts.
            // For a sample, it's safer to return a known "safe" char
            // that is unlikely to be in the negated set.
            return '!'; // e.g., a "safe" punctuation mark
        }

        if (empty($node->parts)) {
            // e.g., [] which can never match
            throw new \RuntimeException('Cannot generate sample for empty character class');
        }

        // Pick one of the parts at random
        $randomKey = array_rand($node->parts);

        return $node->parts[$randomKey]->accept($this);
    }

    public function visitRange(RangeNode $node): string
    {
        if (!$node->start instanceof LiteralNode || !$node->end instanceof LiteralNode) {
            // Should be caught by Validator, but good to check
            return $node->start->accept($this);
        }

        // Generate a random character within the ASCII range
        try {
            $ord1 = \ord($node->start->value);
            $ord2 = \ord($node->end->value);

            return \chr(mt_rand($ord1, $ord2));
        } catch (\Throwable) {
            // Fallback if ord() fails
            return $node->start->value;
        }
    }

    public function visitBackref(BackrefNode $node): string
    {
        $ref = $node->ref;

        // Check numeric reference first
        if (ctype_digit($ref)) {
            $key = (int) $ref;
            if (isset($this->captures[$key])) {
                return $this->captures[$key];
            }
        }

        // Check string/named reference (e.g. for (?&name) conditionals)
        if (isset($this->captures[$ref])) {
            return $this->captures[$ref];
        }

        // Handle named \k<name> or \k{name} backrefs
        // $ref is guaranteed to be a string here.
        if (preg_match('/^k<(\w+)>$/', $ref, $m) || preg_match('/^k\{(\w+)}$/', $ref, $m)) {
            return $this->captures[$m[1]] ?? '';
        }

        // Backreference to a group that hasn't matched yet
        // (or doesn't exist). In a real engine, this fails the match.
        // For generation, we must return empty string.
        return '';
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $node->code, $m)) {
            return \chr((int) hexdec($m[1]));
        }
        if (preg_match('/^\\\\u\{([0-9a-fA-F]+)\}$/', $node->code, $m)) {
            return mb_chr((int) hexdec($m[1]), 'UTF-8');
        }

        // Fallback for unknown unicode
        return '?';
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        // Too complex to generate a *random* char for a property.
        // Return a known-good sample.
        if (str_contains($node->prop, 'L')) { // 'L' (Letter)
            return $this->getRandomChar(['a', 'b', 'c']);
        }
        if (str_contains($node->prop, 'N')) { // 'N' (Number)
            return $this->getRandomChar(['1', '2', '3']);
        }
        if (str_contains($node->prop, 'P')) { // 'P' (Punctuation)
            return $this->getRandomChar(['.', ',', '!']);
        }

        return $this->getRandomChar(['a', '1', '.']); // Generic fallback
    }

    public function visitOctal(OctalNode $node): string
    {
        if (preg_match('/^\\\\o\{([0-7]+)\}$/', $node->code, $m)) {
            return mb_chr((int) octdec($m[1]), 'UTF-8');
        }

        return '?';
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return mb_chr((int) octdec($node->code), 'UTF-8');
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        return match (strtolower($node->class)) {
            'alpha' => $this->getRandomChar(['a', 'b', 'C', 'Z']),
            'alnum' => $this->getRandomChar(['a', 'Z', '1', '9']),
            'digit' => $this->getRandomChar(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
            'xdigit' => $this->getRandomChar(['0', '9', 'a', 'f', 'A', 'F']),
            'space' => $this->getRandomChar([' ', "\t", "\n"]),
            'lower' => $this->getRandomChar(['a', 'b', 'c', 'z']),
            'upper' => $this->getRandomChar(['A', 'B', 'C', 'Z']),
            'punct' => $this->getRandomChar(['.', '!', ',', '?']),
            default => $this->getRandomChar(['a', '1', ' ']),
        };
    }

    public function visitComment(CommentNode $node): string
    {
        // Comments do not generate text
        return '';
    }

    public function visitConditional(ConditionalNode $node): string
    {
        // This is complex. Does the condition (e.g., group 1) exist?
        // We'll randomly choose to satisfy the condition or not.
        try {
            $choice = random_int(0, 1);
        } catch (\Throwable) {
            $choice = 0; // Fallback
        }

        if (1 === $choice) {
            // Simulate "YES" path
            return $node->yes->accept($this);
        }

        // Simulate "NO" path
        return $node->no->accept($this);
    }

    public function visitSubroutine(SubroutineNode $node): string
    {
        // Recursive generation is a deep computer science problem
        // (can lead to infinite loops). Safest to throw.
        throw new \LogicException('Sample generation for subroutines is not supported.');
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        // Verbs do not generate text
        return '';
    }

    /**
     * @param array<string> $chars
     */
    private function getRandomChar(array $chars): string
    {
        if (empty($chars)) {
            return '?'; // Safe fallback
        }

        return $chars[array_rand($chars)];
    }

    private function generateForCharType(string $type): string
    {
        try {
            return match ($type) {
                'd' => (string) random_int(0, 9),
                'D' => $this->getRandomChar(['a', ' ', '!']), // Not a digit
                's' => $this->getRandomChar([' ', "\t", "\n"]),
                'S' => $this->getRandomChar(['a', '1', '!']), // Not whitespace
                'w' => $this->getRandomChar(['a', 'Z', '5', '_']),
                'W' => $this->getRandomChar(['!', ' ', '@']), // Not word
                'h' => $this->getRandomChar([' ', "\t"]),
                'H' => $this->getRandomChar(['a', '1', "\n"]), // Not horiz space
                'v' => "\n", // vertical space
                'V' => $this->getRandomChar(['a', '1', ' ']), // Not vert space
                'R' => $this->getRandomChar(["\r\n", "\r", "\n"]),
                default => '?',
            };
        } catch (\Throwable) {
            return '?'; // Fallback for random_int failure
        }
    }
}
