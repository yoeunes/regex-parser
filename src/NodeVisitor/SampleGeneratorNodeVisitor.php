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
use RegexParser\Node\GroupType;

/**
 * A visitor that generates a random sample string that matches the AST.
 *
 * Purpose: This visitor traverses the Abstract Syntax Tree (AST) of a regular expression
 * and constructs a concrete string that would successfully match the given pattern.
 * It's invaluable for testing, demonstrating regex behavior, and providing examples
 * of valid input for a given regex.
 *
 * @implements NodeVisitorInterface<string>
 */
class SampleGeneratorNodeVisitor implements NodeVisitorInterface
{
    private const int MAX_RECURSION_DEPTH = 2;

    private ?int $seed = null;

    /**
     * Stores generated text from capturing groups.
     * Keyed by both numeric index and name (if available).
     *
     * @var array<int|string, string>
     */
    private array $captures = [];

    private int $groupCounter = 1;

    private int $recursionDepth = 0;

    private ?Node\NodeInterface $rootPattern = null;

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
    public function __construct(private readonly int $maxRepetition = 3) {}

    /**
     * Seeds the Mersenne Twister random number generator.
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
        mt_srand($seed);
    }

    /**
     * Resets the random number generator to its default, unseeded state.
     *
     * Purpose: This method reverts the random number generator to its default behavior,
     * where it is seeded with a truly random value (or based on the system time).
     * This is useful when you want to generate different, non-reproducible samples
     * after having previously set a specific seed.
     */
    public function resetSeed(): void
    {
        $this->seed = null;
        mt_srand();
    }

    /**
     * Visits a RegexNode and initiates the sample generation process.
     *
     * Purpose: This is the entry point for generating a sample string for an entire regular
     * expression. It resets the internal state (like captured groups and group counters)
     * to ensure a clean generation for each new regex. It then delegates the actual
     * string generation to the root pattern of the regex.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return string a sample string that matches the given regular expression
     *
     * @example
     * ```php
     * // Assuming $regexNode is the root of your parsed AST for '/hello/'
     * $visitor = new SampleGeneratorNodeVisitor();
     * $sample = $regexNode->accept($visitor); // $sample could be "hello"
     * ```
     */
    public function visitRegex(Node\RegexNode $node): string
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
        $this->collectGroups($node->pattern);
        $this->totalGroupCount = $this->groupDefinitionCounter - 1;

        // Ensure we are seeded if the user expects it
        if (null !== $this->seed) {
            mt_srand($this->seed);
        }

        // Note: Flags (like /i) are ignored, as we generate the sample
        // from the literal pattern.
        return $node->pattern->accept($this);
    }

    /**
     * Visits an AlternationNode and generates a sample from one of its alternatives.
     *
     * Purpose: When a regex contains an "OR" condition (e.g., `cat|dog`), this method
     * randomly selects one of the available alternatives and generates a sample string
     * from it. This ensures that the generated sample adheres to the branching logic
     * of the regex.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @throws \RuntimeException If the alternation node has no alternatives (e.g., `(|)`).
     *
     * @return string a sample string generated from one of the chosen alternatives
     *
     * @example
     * ```php
     * // For a regex like `(apple|banana|orange)`
     * $alternationNode->accept($visitor); // Could return "apple", "banana", or "orange"
     * ```
     */
    public function visitAlternation(Node\AlternationNode $node): string
    {
        if (empty($node->alternatives)) {
            return '';
        }

        // Pick one of the alternatives at random
        $randomKey = mt_rand(0, \count($node->alternatives) - 1);
        $chosenAlt = $node->alternatives[$randomKey];

        return $chosenAlt->accept($this);
    }

    /**
     * Visits a SequenceNode and concatenates samples from its child nodes.
     *
     * Purpose: This method processes a linear sequence of regex elements (e.g., `abc`).
     * It recursively generates a sample string for each child node in the sequence and
     * then joins them together in order, forming a complete sample for that part of the regex.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return string a sample string formed by concatenating the samples of its children
     *
     * @example
     * ```php
     * // For a regex like `foo` (represented as a sequence of 'f', 'o', 'o' literals)
     * $sequenceNode->accept($visitor); // Returns "foo"
     * ```
     */
    public function visitSequence(Node\SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);

        return implode('', $parts);
    }

    /**
     * Visits a GroupNode and generates a sample from its child, handling capturing.
     *
     * Purpose: This method is responsible for generating samples for various types of groups
     * (capturing, non-capturing, named, lookarounds). It ensures that lookarounds, which are
     * zero-width assertions, do not generate any text. For capturing and named groups, it
     * stores the generated content so it can be referenced later by backreferences.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return string a sample string generated from the group's content, or an empty string
     *                if it's a zero-width assertion
     *
     * @example
     * ```php
     * // For a capturing group `(hello)`
     * $groupNode->accept($visitor); // Returns "hello" and stores "hello" in captures
     *
     * // For a non-capturing group `(?:world)`
     * $groupNode->accept($visitor); // Returns "world"
     *
     * // For a positive lookahead `(?=test)`
     * $groupNode->accept($visitor); // Returns "" (empty string)
     * ```
     */
    public function visitGroup(Node\GroupNode $node): string
    {
        // Lookarounds are zero-width assertions and should not generate text
        if (\in_array($node->type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true)) {
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

    /**
     * Visits a QuantifierNode and generates a sample by repeating its child node.
     *
     * Purpose: This method handles repetition operators like `*`, `+`, `?`, and `{n,m}`.
     * It first determines the minimum and maximum number of repetitions allowed by the
     * quantifier, then randomly selects a number within that range (respecting `maxRepetition`).
     * The child node is then visited that many times, and its generated samples are concatenated.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return string a sample string formed by repeating the quantified element
     *
     * @example
     * ```php
     * // For a quantifier `a{1,3}`
     * $quantifierNode->accept($visitor); // Could return "a", "aa", or "aaa"
     *
     * // For `b*` (with maxRepetition = 3)
     * $quantifierNode->accept($visitor); // Could return "", "b", "bb", or "bbb"
     * ```
     */
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        [$min, $max] = $this->parseQuantifierRange($node->quantifier);

        // Pick a random number of repetitions
        try {
            // $min and $max are guaranteed to be in the correct order
            // by parseQuantifierRange()
            $repeats = ($min === $max) ? $min : mt_rand($min, $max);
        } catch (\Throwable) {
            $repeats = $min; // Fallback
        }

        $parts = [];
        for ($i = 0; $i < $repeats; $i++) {
            $parts[] = $node->node->accept($this);
        }

        return implode('', $parts);
    }

    /**
     * Visits a LiteralNode and returns its raw value as the sample.
     *
     * Purpose: This is one of the simplest visitor methods. When a literal character
     * or string (e.g., `a`, `hello`) is encountered, its exact value is returned
     * as the sample, as it matches itself directly.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return string the literal value of the node
     *
     * @example
     * ```php
     * // For a literal `x`
     * $literalNode->accept($visitor); // Returns "x"
     * ```
     */
    public function visitLiteral(Node\LiteralNode $node): string
    {
        return $node->value;
    }

    /**
     * Visits a CharTypeNode and generates a sample character matching its type.
     *
     * Purpose: This method handles predefined character types like `\d` (digit),
     * `\s` (whitespace), or `\w` (word character). It intelligently generates a
     * random character that satisfies the criteria of the character type, providing
     * a realistic sample.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return string a single character that matches the specified character type
     *
     * @example
     * ```php
     * // For `\d`
     * $charTypeNode->accept($visitor); // Could return "0", "5", "9", etc.
     *
     * // For `\s`
     * $charTypeNode->accept($visitor); // Could return " ", "\t", "\n", etc.
     * ```
     */
    public function visitCharType(Node\CharTypeNode $node): string
    {
        return $this->generateForCharType($node->value);
    }

    /**
     * Visits a DotNode and generates a sample for the wildcard character.
     *
     * Purpose: The dot (`.`) matches any character (except newline by default).
     * This method generates a simple, printable ASCII character as a sample,
     * representing the broad matching capability of the dot.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return string A single, generic character (e.g., 'a', '1', ' ').
     *
     * @example
     * ```php
     * // For `.`
     * $dotNode->accept($visitor); // Could return "a", "1", " ", etc.
     * ```
     */
    public function visitDot(Node\DotNode $node): string
    {
        // Generate a random, simple, printable ASCII char
        return $this->getRandomChar(['a', 'b', 'c', '1', '2', '3', ' ']);
    }

    /**
     * Visits an AnchorNode. Anchors do not generate text.
     *
     * Purpose: Positional anchors like `^` (start of line) or `$` (end of line)
     * assert a position in the string but do not consume any characters. Therefore,
     * this method always returns an empty string, as anchors contribute no literal
     * text to the sample.
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `^`
     * $anchorNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitAnchor(Node\AnchorNode $node): string
    {
        // Anchors do not generate text
        return '';
    }

    /**
     * Visits an AssertionNode. Assertions do not generate text.
     *
     * Purpose: Zero-width assertions like `\b` (word boundary) or `\A` (start of subject)
     * check for a condition at the current position without consuming characters.
     * Consequently, this method always returns an empty string, as assertions do not
     * contribute literal text to the generated sample.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `\b`
     * $assertionNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitAssertion(Node\AssertionNode $node): string
    {
        // Assertions do not generate text
        return '';
    }

    /**
     * Visits a KeepNode. The `\K` assertion does not generate text.
     *
     * Purpose: The `\K` assertion resets the starting point of the match. While it
     * influences the final matched string, it does not itself consume characters
     * or add literal text to the pattern. Thus, this method returns an empty string.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `\K`
     * $keepNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitKeep(Node\KeepNode $node): string
    {
        // \K does not generate text
        return '';
    }

    /**
     * Visits a CharClassNode and generates a sample character from it.
     *
     * Purpose: This method handles character sets like `[a-z]` or `[^0-9]`.
     * For positive character classes, it randomly selects one of its constituent
     * parts (literals, ranges, char types) to generate a sample. For negated
     * character classes, it returns a safe, generic character as generating a
     * truly random character *not* in the set is complex and context-dependent.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @throws \RuntimeException If attempting to generate a sample for an empty character class (e.g., `[]`).
     *
     * @return string a single character that matches the character class
     *
     * @example
     * ```php
     * // For `[aeiou]`
     * $charClassNode->accept($visitor); // Could return "a", "e", "i", "o", or "u"
     *
     * // For `[^0-9]`
     * $charClassNode->accept($visitor); // Returns "!" (a safe fallback)
     * ```
     */
    public function visitCharClass(Node\CharClassNode $node): string
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
        $randomKey = mt_rand(0, \count($node->parts) - 1);

        return $node->parts[$randomKey]->accept($this);
    }

    /**
     * Visits a RangeNode and generates a random character within the specified range.
     *
     * Purpose: Within a character class, ranges like `a-z` specify a continuous set of characters.
     * This method generates a random character whose ASCII (or Unicode) ordinal value falls
     * inclusively between the start and end characters of the range.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return string a single character within the specified range
     *
     * @example
     * ```php
     * // For a range `A-Z`
     * $rangeNode->accept($visitor); // Could return "A", "M", "Z", etc.
     * ```
     */
    public function visitRange(Node\RangeNode $node): string
    {
        if (!$node->start instanceof Node\LiteralNode || !$node->end instanceof Node\LiteralNode) {
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

    /**
     * Visits a BackrefNode and retrieves the captured string from a previous group.
     *
     * Purpose: Backreferences (e.g., `\1`, `\k<name>`) match the exact text that was
     * previously captured by a specific group. This method looks up the stored captured
     * content using the backreference's identifier (numeric or named) and returns it.
     * If the group hasn't captured anything yet or doesn't exist, it returns an empty string.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return string the captured string from the referenced group, or an empty string
     *                if the group has not captured anything or does not exist
     *
     * @example
     * ```php
     * // For a regex like `(a+)\1`
     * // If group 1 captured "aaa", then `\1` will return "aaa".
     * $backrefNode->accept($visitor); // Returns the content of the referenced group
     * ```
     */
    public function visitBackref(Node\BackrefNode $node): string
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
        if (preg_match('/^\\\\k<(\w+)>$/', $ref, $m) || preg_match('/^\\\\k\{(\w+)\}$/', $ref, $m)) {
            return $this->captures[$m[1]] ?? '';
        }

        // Backreference to a group that hasn't matched yet
        // (or doesn't exist). In a real engine, this fails the match.
        // For generation, we must return empty string.
        return '';
    }

    /**
     * Visits a UnicodeNode and generates the corresponding Unicode character.
     *
     * Purpose: This method handles Unicode character escapes (e.g., `\x{2603}`, `\u{1F600}`).
     * It parses the hexadecimal code point from the node and converts it into the actual
     * Unicode character, ensuring that the generated sample correctly represents the
     * specified character.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return string the Unicode character corresponding to the node's code, or '?' as a fallback
     *
     * @example
     * ```php
     * // For `\x{2603}` (snowman)
     * $unicodeNode->accept($visitor); // Returns "☃"
     * ```
     */
    public function visitUnicode(Node\UnicodeNode $node): string
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

    /**
     * Visits a UnicodePropNode and generates a sample character matching its property.
     *
     * Purpose: This method handles Unicode character properties (e.g., `\p{L}` for any letter,
     * `\p{N}` for any number). Since generating a truly random character for every possible
     * Unicode property is extremely complex, this method provides a known-good, representative
     * sample character for common properties.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return string a single character that is representative of the specified Unicode property
     *
     * @example
     * ```php
     * // For `\p{L}` (any Unicode letter)
     * $unicodePropNode->accept($visitor); // Could return "a", "b", "c", etc.
     *
     * // For `\p{N}` (any Unicode number)
     * $unicodePropNode->accept($visitor); // Could return "1", "2", "3", etc.
     * ```
     */
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
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

    /**
     * Visits an OctalNode and generates the character represented by its octal code.
     *
     * Purpose: This method processes modern octal character escapes (e.g., `\o{101}`).
     * It converts the octal string into its corresponding character, allowing the
     * sample generator to correctly interpret and represent these specific characters.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return string the character represented by the octal code, or '?' as a fallback
     *
     * @example
     * ```php
     * // For `\o{101}` (which is 'A' in ASCII)
     * $octalNode->accept($visitor); // Returns "A"
     * ```
     */
    public function visitOctal(Node\OctalNode $node): string
    {
        if (preg_match('/^\\\\o\{([0-7]+)\}$/', $node->code, $m)) {
            return mb_chr((int) octdec($m[1]), 'UTF-8');
        }

        return '?';
    }

    /**
     * Visits an OctalLegacyNode and generates the character represented by its legacy octal code.
     *
     * Purpose: This method handles legacy octal character escapes (e.g., `\012`).
     * It converts the octal string into its corresponding character, ensuring compatibility
     * with older regex syntax for character representation.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return string the character represented by the legacy octal code
     *
     * @example
     * ```php
     * // For `\012` (which is newline in ASCII)
     * $octalLegacyNode->accept($visitor); // Returns "\n"
     * ```
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return mb_chr((int) octdec($node->code), 'UTF-8');
    }

    /**
     * Visits a PosixClassNode and generates a sample character matching its POSIX class.
     *
     * Purpose: This method handles POSIX character classes (e.g., `[:alpha:]`, `[:digit:]`).
     * It provides a representative sample character for each common POSIX class, allowing
     * the sample generator to produce valid characters for these predefined sets.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return string a single character that matches the specified POSIX class
     *
     * @example
     * ```php
     * // For `[:digit:]`
     * $posixClassNode->accept($visitor); // Could return "0", "5", "9", etc.
     *
     * // For `[:alpha:]`
     * $posixClassNode->accept($visitor); // Could return "a", "B", "z", etc.
     * ```
     */
    public function visitPosixClass(Node\PosixClassNode $node): string
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

    /**
     * Visits a CommentNode. Comments do not generate text.
     *
     * Purpose: Comments within a regex (e.g., `(?#comment)`) are ignored by the regex engine
     * during matching. Similarly, this visitor ignores them during sample generation,
     * as they do not contribute to the actual string that matches the pattern.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `(?#This is a comment)`
     * $commentNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitComment(Node\CommentNode $node): string
    {
        // Comments do not generate text
        return '';
    }

    /**
     * Visits a ConditionalNode and generates a sample based on a random choice.
     *
     * Purpose: Conditional patterns (e.g., `(?(condition)yes|no)`) introduce branching logic.
     * Since the actual condition might depend on runtime context (like whether a group
     * has captured), this method simplifies by randomly choosing to generate a sample
     * from either the "if true" branch or the "if false" branch.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return string a sample string generated from either the 'yes' or 'no' branch
     *
     * @example
     * ```php
     * // For `(?(1)foo|bar)`
     * $conditionalNode->accept($visitor); // Could return "foo" or "bar"
     * ```
     */
    public function visitConditional(Node\ConditionalNode $node): string
    {
        // This is complex. Does the condition (e.g., group 1) exist?
        // We'll randomly choose to satisfy the condition or not.
        try {
            $choice = mt_rand(0, 1);
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

    /**
     * Visits a SubroutineNode with depth-limited recursion support.
     *
     * Purpose: Subroutines (e.g., `(?&name)` or `(?R)`) allow recursive pattern calls.
     * This implementation resolves the referenced group or whole pattern and generates
     * a sample up to a configurable maximum recursion depth to avoid infinite expansion.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     */
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        if (null === $this->rootPattern || $this->rootPattern instanceof Node\SubroutineNode) {
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
        $result = $target instanceof Node\GroupNode ? $target->child->accept($this) : $target->accept($this);
        $this->recursionDepth--;

        return $result;
    }

    /**
     * Visits a PcreVerbNode. PCRE verbs do not generate text.
     *
     * Purpose: PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`) influence the regex engine's
     * backtracking behavior but do not consume characters or add literal text to the match.
     * As such, this method returns an empty string, as these verbs do not contribute to the
     * generated sample string.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `(*FAIL)`
     * $pcreVerbNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        // Verbs do not generate text
        return '';
    }

    /**
     * Visits a DefineNode. DEFINE blocks do not generate text.
     *
     * Purpose: The `(?(DEFINE)...)` block is used to define named sub-patterns that can
     * be referenced later by subroutines. This block itself does not match any text
     * and is ignored by the regex engine during a normal match attempt. Therefore,
     * this method returns an empty string, as it does not contribute to the sample.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return string an empty string
     *
     * @example
     * ```php
     * // For `(?(DEFINE)(?<digit>\d))`
     * $defineNode->accept($visitor); // Returns ""
     * ```
     */
    public function visitDefine(Node\DefineNode $node): string
    {
        // DEFINE blocks do not generate text, they only define subpatterns
        return '';
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return '';
    }

    public function visitCallout(Node\CalloutNode $node): string
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
            default => preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $q, $m) ?
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
        $key = mt_rand(0, \count($chars) - 1);

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
                'd' => (string) mt_rand(0, 9),
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
            return '?'; // Fallback for mt_rand failure
        }
        // @codeCoverageIgnoreEnd
    }

    private function collectGroups(Node\NodeInterface $node): void
    {
        if ($node instanceof Node\GroupNode) {
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

        if ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                $this->collectGroups($child);
            }

            return;
        }

        if ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $this->collectGroups($alt);
            }

            return;
        }

        if ($node instanceof Node\QuantifierNode) {
            $this->collectGroups($node->node);

            return;
        }

        if ($node instanceof Node\ConditionalNode) {
            $this->collectGroups($node->condition);
            $this->collectGroups($node->yes);
            $this->collectGroups($node->no);

            return;
        }

        if ($node instanceof Node\DefineNode) {
            $this->collectGroups($node->content);
        }
    }

    private function resolveSubroutineTarget(Node\SubroutineNode $node): ?Node\NodeInterface
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
}
