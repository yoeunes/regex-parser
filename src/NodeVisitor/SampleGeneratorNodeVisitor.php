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

use Random\Engine\Mt19937;
use Random\Randomizer;
use RegexParser\Node;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;

/**
 * A visitor that generates a random sample string that matches the AST.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class SampleGeneratorNodeVisitor extends AbstractNodeVisitor
{
    private const MAX_RECURSION_DEPTH = 2;

    private ?int $seed = null;

    private Randomizer $randomizer;

    /**
     * Stores generated text from capturing groups.
     * Keyed by both numeric index and name (if available).
     *
     * @var array<int|string, string>
     */
    private array $captures = [];

    private int $groupCounter = 1;

    private int $recursionDepth = 0;

    private ?NodeInterface $rootPattern = null;

    /**
     * @var array<int, Node\GroupNode>
     */
    private array $groupIndexMap = [];

    /**
     * @var array<string, Node\GroupNode>
     */
    private array $namedGroupMap = [];

    /**
     * @var array<int, int>
     */
    private array $groupNumbers = [];

    private int $groupDefinitionCounter = 1;

    private int $totalGroupCount = 0;

    /**
     * @var array<int, string>
     */
    private array $requiredPrefixes = [];

    /**
     * @var array<int, string>
     */
    private array $requiredSuffixes = [];

    /**
     * Constructs a new SampleGeneratorNodeVisitor.
     *
     * Purpose: Initializes the visitor with a maximum repetition limit. This limit is crucial
     * for preventing infinite loops or excessively long sample strings when dealing with
     * quantifiers like `*` (zero or more) or `+` (one or more), ensuring that sample generation
     * remains practical and performant.
     *
     * @param int $maxRepetition The maximum number of times a quantifier like `*` or `+`
     *                           should repeat its preceding element. This prevents
     *                           excessively long or infinite samples.
     */
    public function __construct(private readonly int $maxRepetition = 3)
    {
        $this->resetRandomizer();
    }

    /**
     * Seeds the local random number generator.
     *
     * Purpose: This method allows for deterministic sample generation. By setting a specific
     * seed, you can ensure that the `SampleGeneratorNodeVisitor` will produce the exact same
     * sample string for a given regex every time it's run with that seed. This is highly
     * beneficial for reproducible testing and debugging.
     *
     * @param int $seed the integer seed value to use for the random number generator
     */
    public function setSeed(int $seed): void
    {
        $this->seed = $seed;
        $this->resetRandomizer($seed);
    }

    /**
     * Resets the random number generator to its default, unseeded state.
     *
     * Purpose: This method reverts the local RNG to its default behavior,
     * where it is seeded with a random value. This is useful when you want to
     * generate different, non-reproducible samples after having previously set
     * a specific seed.
     */
    public function resetSeed(): void
    {
        $this->seed = null;
        $this->resetRandomizer();
    }

    #[\Override]
    public function visitRegex(RegexNode $node): string
    {
        // Reset state for this run
        $this->captures = [];
        $this->groupCounter = 1;
        $this->recursionDepth = 0;
        $this->rootPattern = $node->pattern;
        $this->groupIndexMap = [];
        $this->namedGroupMap = [];
        $this->groupNumbers = [];
        $this->groupDefinitionCounter = 1;
        $this->requiredPrefixes = [];
        $this->requiredSuffixes = [];
        $this->collectGroups($node->pattern);
        $this->totalGroupCount = $this->groupDefinitionCounter - 1;

        // Ensure we are seeded if the user expects it
        if (null !== $this->seed) {
            $this->resetRandomizer($this->seed);
        }

        // Note: Flags (like /i) are ignored, as we generate the sample
        // from the literal pattern.
        $sample = $node->pattern->accept($this);

        return $this->applyLookaroundHints($sample);
    }

    #[\Override]
    public function visitAlternation(AlternationNode $node): string
    {
        if (empty($node->alternatives)) {
            return '';
        }

        // Pick one of the alternatives at random
        $randomKey = $this->randomInt(0, \count($node->alternatives) - 1);
        $chosenAlt = $node->alternatives[$randomKey];

        return $chosenAlt->accept($this);
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): string
    {
        $parts = array_map(fn (NodeInterface $child): string => $child->accept($this), $node->children);

        return implode('', $parts);
    }

    #[\Override]
    public function visitGroup(GroupNode $node): string
    {
        // Lookarounds are zero-width assertions and should not generate text
        if (\in_array($node->type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true)) {
            if (GroupType::T_GROUP_LOOKBEHIND_POSITIVE === $node->type) {
                $prefix = $node->child->accept($this);
                if ('' !== $prefix) {
                    $this->requiredPrefixes[] = $prefix;
                }
            } elseif (GroupType::T_GROUP_LOOKAHEAD_POSITIVE === $node->type) {
                $suffix = $node->child->accept($this);
                if ('' !== $suffix) {
                    $this->requiredSuffixes[] = $suffix;
                }
            }

            return '';
        }

        $result = $node->child->accept($this);

        // Store the result if it's a capturing group
        if (GroupType::T_GROUP_CAPTURING === $node->type) {
            $groupIndex = $this->groupNumbers[spl_object_id($node)] ?? $this->groupCounter++;
            $this->captures[$groupIndex] = $result;
            $this->groupCounter = max($this->groupCounter, $groupIndex + 1);
        } elseif (GroupType::T_GROUP_NAMED === $node->type) {
            $groupIndex = $this->groupNumbers[spl_object_id($node)] ?? $this->groupCounter++;
            $this->captures[$groupIndex] = $result;
            if ($node->name) {
                $this->captures[$node->name] = $result;
            }
            $this->groupCounter = max($this->groupCounter, $groupIndex + 1);
        }

        // For non-capturing, etc., just return the child's result
        return $result;
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): string
    {
        [$min, $max] = $this->parseQuantifierRange($node->quantifier);

        // Pick a random number of repetitions
        // $min and $max are guaranteed to be in the correct order
        // by parseQuantifierRange()
        $repeats = ($min === $max) ? $min : $this->randomInt($min, $max);

        $parts = [];
        for ($i = 0; $i < $repeats; $i++) {
            $parts[] = $node->node->accept($this);
        }

        return implode('', $parts);
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): string
    {
        return $node->value;
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node): string
    {
        return $this->generateForCharType($node->value);
    }

    #[\Override]
    public function visitDot(DotNode $node): string
    {
        // Generate a random, simple, printable ASCII char
        return $this->getRandomChar(['a', 'b', 'c', '1', '2', '3', ' ']);
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): string
    {
        // Anchors do not generate text
        return '';
    }

    #[\Override]
    public function visitAssertion(AssertionNode $node): string
    {
        // Assertions do not generate text
        return '';
    }

    #[\Override]
    public function visitKeep(KeepNode $node): string
    {
        // \K does not generate text
        return '';
    }

    #[\Override]
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

        $parts = $node->expression instanceof AlternationNode ? $node->expression->alternatives : [$node->expression];
        if (empty($parts)) {
            // e.g., [] which can never match
            throw new \RuntimeException('Cannot generate sample for empty character class');
        }

        // Pick one of the parts at random
        $randomKey = $this->randomInt(0, \count($parts) - 1);

        return $parts[$randomKey]->accept($this);
    }

    #[\Override]
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

            return \chr($this->randomInt($ord1, $ord2));
        } catch (\Throwable) {
            // Fallback if ord() fails
            return $node->start->value;
        }
    }

    #[\Override]
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

        // Check numeric reference with \
        if (preg_match('/^\\\\(\d++)$/', $ref, $matches)) {
            $key = (int) $matches[1];
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
        if (preg_match('/^\\\\k<(\w++)>$/', $ref, $m) || preg_match('/^\\\\k\{(\w++)\}$/', $ref, $m)) {
            return $this->captures[$m[1]] ?? '';
        }

        // Backreference to a group that hasn't matched yet
        // (or doesn't exist). In a real engine, this fails the match.
        // For generation, we must return empty string.
        return '';
    }

    #[\Override]
    public function visitControlChar(ControlCharNode $node): string
    {
        if ($node->codePoint < 0 || $node->codePoint > 0xFF) {
            return '?';
        }

        return \chr($node->codePoint);
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node): string
    {
        if ($node->codePoint < 0 || $node->codePoint > 0x10FFFF) {
            return '?';
        }

        try {
            return mb_chr($node->codePoint, 'UTF-8');
        } catch (\Throwable) {
            return '?';
        }
    }

    #[\Override]
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

    #[\Override]
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
            'word' => $this->getRandomChar(['a', 'Z', '0', '9', '_']),
            'blank' => $this->getRandomChar([' ', "\t"]),
            'cntrl' => "\x00", // Control character
            'graph', 'print' => $this->getRandomChar(['!', '@', '#']),
            default => $this->getRandomChar(['a', '1', ' ']),
        };
    }

    #[\Override]
    public function visitComment(CommentNode $node): string
    {
        // Comments do not generate text
        return '';
    }

    #[\Override]
    public function visitConditional(ConditionalNode $node): string
    {
        if ($this->isConditionSatisfied($node->condition)) {
            return $node->yes->accept($this);
        }

        return $node->no->accept($this);
    }

    #[\Override]
    public function visitSubroutine(SubroutineNode $node): string
    {
        if (null === $this->rootPattern || $this->rootPattern instanceof SubroutineNode) {
            throw new \LogicException('Sample generation for subroutines is not supported.');
        }

        if ($this->recursionDepth >= self::MAX_RECURSION_DEPTH) {
            return '';
        }

        $target = $this->resolveSubroutineTarget($node);
        if (null === $target) {
            throw new \LogicException('Sample generation for subroutines is not supported.');
        }

        $this->recursionDepth++;
        $result = $target instanceof GroupNode ? $target->child->accept($this) : $target->accept($this);
        $this->recursionDepth--;

        return $result;
    }

    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        // Verbs do not generate text
        return '';
    }

    #[\Override]
    public function visitDefine(DefineNode $node): string
    {
        // DEFINE blocks do not generate text, they only define subpatterns
        return '';
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): string
    {
        return '';
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): string
    {
        // Callouts do not match characters, so they generate no sample text.
        return '';
    }

    /**
     * Parses a quantifier string into its minimum and maximum repetition counts.
     *
     * Purpose: This helper method interprets the various quantifier syntaxes (e.g., `*`, `+`, `?`, `{n}`, `{n,}`, `{n,m}`)
     * and converts them into a standardized `[min, max]` array. It also applies the `maxRepetition`
     * limit to prevent excessively long samples for unbounded quantifiers.
     *
     * @param string $q The quantifier string (e.g., `*`, `+`, `{1,5}`).
     *
     * @return array{0: int, 1: int} an array containing the minimum and maximum repetition counts
     */
    private function parseQuantifierRange(string $q): array
    {
        $range = match ($q) {
            '*' => [0, $this->maxRepetition],
            '+' => [1, $this->maxRepetition],
            '?' => [0, 1],
            default => preg_match('/^\{(\d++)(?:,(\d*+))?\}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    [(int) $m[1], (int) $m[1] + $this->maxRepetition] : // {n,}
                    [(int) $m[1], (int) $m[2]] // {n,m}
                ) :
                    [(int) $m[1], (int) $m[1]] // {n}
                ) :
                // @codeCoverageIgnoreStart
                [0, 0], // Fallback
            // @codeCoverageIgnoreEnd
        };

        // Ensure min <= max, as Validator may not have run.
        // This handles invalid cases like {5,2} and silences PHPStan
        if ($range[1] < $range[0]) {
            $range[1] = $range[0];
        }

        return $range;
    }

    /**
     * Generates a random integer using the local RNG.
     */
    private function randomInt(int $min, int $max): int
    {
        if ($max < $min) {
            $max = $min;
        }

        try {
            return $this->randomizer->getInt($min, $max);
        } catch (\Throwable) {
            return $min;
        }
    }

    private function applyLookaroundHints(string $sample): string
    {
        foreach ($this->requiredPrefixes as $prefix) {
            if ('' === $prefix) {
                continue;
            }

            if (!str_starts_with($sample, $prefix)) {
                $sample = $prefix.$sample;
            }
        }

        foreach ($this->requiredSuffixes as $suffix) {
            if ('' === $suffix) {
                continue;
            }

            if (!str_contains($sample, $suffix)) {
                $sample .= $suffix;
            }
        }

        return $sample;
    }

    private function isConditionSatisfied(NodeInterface $condition): bool
    {
        if ($condition instanceof BackrefNode) {
            return $this->hasCaptureForReference($condition->ref);
        }

        if ($condition instanceof GroupNode) {
            if (\in_array($condition->type, [
                GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
                GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            ], true)) {
                return true;
            }

            if (\in_array($condition->type, [
                GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
                GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
            ], true)) {
                return false;
            }

            return '' !== $condition->accept($this);
        }

        if ($condition instanceof AssertionNode) {
            return true;
        }

        return 1 === $this->randomInt(0, 1);
    }

    private function hasCaptureForReference(string $reference): bool
    {
        if (ctype_digit($reference)) {
            return isset($this->captures[(int) $reference]);
        }

        if (isset($this->captures[$reference])) {
            return true;
        }

        if (preg_match('/^\\\\(\d++)$/', $reference, $matches)) {
            return isset($this->captures[(int) $matches[1]]);
        }

        return false;
    }

    /**
     * Selects a random character from a given array of characters.
     *
     * Purpose: This utility method provides a simple way to pick one character
     * from a predefined set. It's used by various `visit` methods to generate
     * a representative character when a specific type or class of character is needed.
     *
     * @param array<string> $chars an array of single-character strings to choose from
     *
     * @return string a randomly selected character from the input array, or '?' if the array is empty
     */
    private function getRandomChar(array $chars): string
    {
        if (empty($chars)) {
            return '?'; // Safe fallback
        }
        $key = $this->randomInt(0, \count($chars) - 1);

        return $chars[$key];
    }

    /**
     * Generates a sample character for a given character type (e.g., `d`, `s`, `w`).
     *
     * Purpose: This helper method centralizes the logic for generating sample characters
     * for various `\d`, `\s`, `\w`, etc., character types. It provides a specific
     * random character that fits the definition of the character type.
     *
     * @param string $type The character type identifier (e.g., 'd', 'D', 's', 'S').
     *
     * @return string a single character matching the specified type, or '?' as a fallback
     */
    private function generateForCharType(string $type): string
    {
        try {
            return match ($type) {
                'd' => (string) $this->randomInt(0, 9),
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
            // @codeCoverageIgnoreStart
        } catch (\Throwable) {
            return '?'; // Fallback for random generation failure
        }
        // @codeCoverageIgnoreEnd
    }

    private function collectGroups(NodeInterface $node): void
    {
        if ($node instanceof GroupNode) {
            if (\in_array($node->type, [GroupType::T_GROUP_CAPTURING, GroupType::T_GROUP_NAMED], true)) {
                $index = $this->groupDefinitionCounter++;
                $this->groupIndexMap[$index] = $node;
                $this->groupNumbers[spl_object_id($node)] = $index;
                if (null !== $node->name) {
                    $this->namedGroupMap[$node->name] = $node;
                }
            }

            $this->collectGroups($node->child);

            return;
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                $this->collectGroups($child);
            }

            return;
        }

        if ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $this->collectGroups($alt);
            }

            return;
        }

        if ($node instanceof QuantifierNode) {
            $this->collectGroups($node->node);

            return;
        }

        if ($node instanceof ConditionalNode) {
            $this->collectGroups($node->condition);
            $this->collectGroups($node->yes);
            $this->collectGroups($node->no);

            return;
        }

        if ($node instanceof DefineNode) {
            $this->collectGroups($node->content);
        }
    }

    private function resolveSubroutineTarget(SubroutineNode $node): ?NodeInterface
    {
        $ref = $node->reference;

        if ('R' === $ref || '0' === $ref) {
            return $this->rootPattern;
        }

        if (str_starts_with($ref, 'R')) {
            $ref = substr($ref, 1);
            if ('' === $ref) {
                return $this->rootPattern;
            }
        }

        if (ctype_digit($ref)) {
            $index = (int) $ref;

            return $this->groupIndexMap[$index] ?? null;
        }

        if (str_starts_with($ref, '-') && ctype_digit(substr($ref, 1))) {
            $resolvedIndex = $this->totalGroupCount + (int) $ref + 1;
            if ($resolvedIndex >= 1) {
                return $this->groupIndexMap[$resolvedIndex] ?? null;
            }

            return null;
        }

        return $this->namedGroupMap[$ref] ?? null;
    }

    private function resetRandomizer(?int $seed = null): void
    {
        $engine = null === $seed ? new Mt19937() : new Mt19937($seed);
        $this->randomizer = new Randomizer($engine);
    }
}
