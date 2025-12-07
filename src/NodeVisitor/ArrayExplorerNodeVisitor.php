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
 * Transforms the AST into a structured array tree suitable for UI visualization.
 *
 * This visitor acts as a Serializer, converting the Domain Model (AST)
 * into a View Model (Array) for the frontend.
 *
 * @extends AbstractNodeVisitor<array<string, mixed>>
 */
final class ArrayExplorerNodeVisitor extends AbstractNodeVisitor
{
    /**
     * Visits a RegexNode and converts it into an array representation.
     *
     * Purpose: This method is the entry point for visualizing the entire regex pattern.
     * It takes the root `RegexNode` and transforms it into a structured array,
     * providing metadata about the overall pattern (like flags) and recursively
     * processing its main content. This is useful for displaying the top-level
     * structure of the regex in a UI.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * $visitor = new ArrayExplorerNodeVisitor();
     * $arrayRepresentation = $regexNode->accept($visitor);
     * // $arrayRepresentation will be an array like:
     * // [
     * //   'type' => 'Regex',
     * //   'label' => 'Pattern',
     * //   'detail' => 'Flags: i',
     * //   'children' => [...]
     * // ]
     * ```
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): array
    {
        return [
            'type' => 'Regex',
            'label' => 'Pattern',
            'detail' => $node->flags ? "Flags: {$node->flags}" : 'Global',
            'icon' => 'fa-solid fa-globe',
            'color' => 'text-indigo-600',
            'bg' => 'bg-indigo-50',
            'children' => [$node->pattern->accept($this)],
        ];
    }

    /**
     * Visits a GroupNode and converts it into an array representation.
     *
     * Purpose: This method processes different types of grouping constructs in the regex,
     * such as capturing groups, non-capturing groups, lookaheads, and lookbehinds.
     * It assigns appropriate labels, icons, and colors based on the group's `GroupType`,
     * making it easy to distinguish their purpose in a visual representation.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * // For a capturing group like `(abc)`
     * $groupNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Group',
     * //   'label' => 'Capturing Group',
     * //   'icon' => 'fa-solid fa-brackets-round',
     * //   'color' => 'text-green-600',
     * //   'children' => [...]
     * // ]
     * ```
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): array
    {
        [$label, $icon, $color, $bg] = match ($node->type) {
            GroupType::T_GROUP_CAPTURING => ['Capturing Group', 'fa-solid fa-brackets-round', 'text-green-600', 'bg-green-50'],
            GroupType::T_GROUP_NAMED => ["Named Group: <span class='font-mono'>{$node->name}</span>", 'fa-solid fa-tag', 'text-emerald-600', 'bg-emerald-50'],
            GroupType::T_GROUP_NON_CAPTURING => ['Non-Capturing Group', 'fa-solid fa-ban', 'text-slate-500', 'bg-slate-50'],
            GroupType::T_GROUP_ATOMIC => ['Atomic Group (?>...)', 'fa-solid fa-lock', 'text-red-500', 'bg-red-50'],
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => ['Positive Lookahead (?=...)', 'fa-solid fa-eye', 'text-blue-600', 'bg-blue-50'],
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => ['Negative Lookahead (?!...)', 'fa-solid fa-eye-slash', 'text-red-600', 'bg-red-50'],
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => ['Positive Lookbehind (?<=...)', 'fa-solid fa-chevron-left', 'text-blue-600', 'bg-blue-50'],
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => ['Negative Lookbehind (?<!...)', 'fa-solid fa-chevron-left', 'text-red-600', 'bg-red-50'],
            default => [ucfirst(str_replace('_', ' ', $node->type->value)), 'fa-solid fa-layer-group', 'text-blue-500', 'bg-blue-50'],
        };

        return [
            'type' => 'Group',
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'bg' => $bg,
            'children' => [$node->child->accept($this)],
        ];
    }

    /**
     * Visits a QuantifierNode and converts it into an array representation.
     *
     * Purpose: This method visualizes how many times a preceding element is allowed to repeat.
     * It extracts the quantifier string (e.g., `*`, `+`, `{1,5}`) and its "greediness" type
     * (greedy, lazy, possessive) to provide a clear description for the UI.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * // For a quantifier like `a+?`
     * $quantifierNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Quantifier',
     * //   'label' => 'Quantifier',
     * //   'detail' => '+ (Lazy)',
     * //   'icon' => 'fa-solid fa-rotate-right',
     * //   'children' => [...] // The quantified node (e.g., LiteralNode for 'a')
     * // ]
     * ```
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): array
    {
        return [
            'type' => 'Quantifier',
            'label' => 'Quantifier',
            'detail' => "{$node->quantifier} (".ucfirst($node->type->value).')',
            'icon' => 'fa-solid fa-rotate-right',
            'color' => 'text-orange-600',
            'bg' => 'bg-orange-50',
            'children' => [$node->node->accept($this)],
        ];
    }

    /**
     * Visits a SequenceNode and converts it into an array representation.
     *
     * Purpose: This method represents a linear progression of regex elements that must match
     * consecutively. It recursively processes all child nodes within the sequence, allowing
     * the UI to display them in their correct order.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, and children (recursively processed) for UI display
     *
     * @example
     * ```php
     * // For a sequence like `abc`
     * $sequenceNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Sequence',
     * //   'label' => 'Sequence',
     * //   'icon' => 'fa-solid fa-arrow-right-long',
     * //   'children' => [ // Array of LiteralNodes for 'a', 'b', 'c'
     * //     ['type' => 'Literal', 'detail' => '"a"', ...],
     * //     ['type' => 'Literal', 'detail' => '"b"', ...],
     * //     ['type' => 'Literal', 'detail' => '"c"', ...],
     * //   ]
     * // ]
     * ```
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): array
    {
        return [
            'type' => 'Sequence',
            'label' => 'Sequence',
            'icon' => 'fa-solid fa-arrow-right-long',
            'color' => 'text-slate-400',
            'children' => array_map(fn ($child) => $child->accept($this), $node->children),
        ];
    }

    /**
     * Visits an AlternationNode and converts it into an array representation.
     *
     * Purpose: This method visualizes the "OR" logic in a regex, where one of several
     * alternative patterns can match. It recursively processes each alternative, enabling
     * the UI to show branching paths.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * // For an alternation like `cat|dog`
     * $alternationNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Alternation',
     * //   'label' => 'Alternation (OR)',
     * //   'icon' => 'fa-solid fa-code-branch',
     * //   'children' => [ // Array of SequenceNodes/LiteralNodes for 'cat' and 'dog'
     * //     ['type' => 'Literal', 'detail' => '"cat"', ...],
     * //     ['type' => 'Literal', 'detail' => '"dog"', ...],
     * //   ]
     * // ]
     * ```
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): array
    {
        return [
            'type' => 'Alternation',
            'label' => 'Alternation (OR)',
            'icon' => 'fa-solid fa-code-branch',
            'color' => 'text-purple-600',
            'bg' => 'bg-purple-50',
            'children' => array_map(fn ($child) => $child->accept($this), $node->alternatives),
        ];
    }

    /**
     * Visits a LiteralNode and converts it into an array representation.
     *
     * Purpose: This method represents a direct match of specific characters. It formats
     * the literal value for display, including handling special characters like newlines
     * for better readability in the UI.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a literal `a`
     * $literalNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Literal',
     * //   'label' => 'Literal',
     * //   'detail' => '"a"',
     * //   'icon' => 'fa-solid fa-font',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): array
    {
        return [
            'type' => 'Literal',
            'label' => 'Literal',
            'detail' => $this->formatValue($node->value),
            'icon' => 'fa-solid fa-font',
            'color' => 'text-slate-700',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a CharClassNode and converts it into an array representation.
     *
     * Purpose: This method visualizes character sets (e.g., `[a-z]`, `[^0-9]`). It determines
     * if the class is negated and assigns appropriate labels, icons, and colors to clearly
     * convey its meaning in the UI.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * // For a character class `[a-zA-Z]`
     * $charClassNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'CharClass',
     * //   'label' => 'Character Set [...]',
     * //   'icon' => 'fa-solid fa-border-all',
     * //   'color' => 'text-teal-600',
     * //   'children' => [...] // Array of RangeNodes, LiteralNodes, etc.
     * // ]
     * ```
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): array
    {
        $label = $node->isNegated ? 'Negative Character Set [^...]' : 'Character Set [...]';
        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];

        return [
            'type' => 'CharClass',
            'label' => $label,
            'icon' => 'fa-solid fa-border-all',
            'color' => $node->isNegated ? 'text-red-600' : 'text-teal-600',
            'bg' => $node->isNegated ? 'bg-red-50' : 'bg-teal-50',
            'children' => array_map(fn ($child) => $child->accept($this), $parts),
        ];
    }

    /**
     * Visits a RangeNode and converts it into an array representation.
     *
     * Purpose: This method visualizes character ranges within a character class (e.g., `a-z`).
     * It processes the start and end characters of the range, providing a clear representation
     * of the inclusive character set.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, and children (recursively processed) for UI display
     *
     * @example
     * ```php
     * // For a range `a-z` inside a character class
     * $rangeNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Range',
     * //   'label' => 'Range',
     * //   'icon' => 'fa-solid fa-arrows-left-right',
     * //   'color' => 'text-teal-600',
     * //   'children' => [ // LiteralNode for 'a', LiteralNode for 'z'
     * //     ['type' => 'Literal', 'detail' => '"a"', ...],
     * //     ['type' => 'Literal', 'detail' => '"z"', ...],
     * //   ]
     * // ]
     * ```
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): array
    {
        return [
            'type' => 'Range',
            'label' => 'Range',
            'icon' => 'fa-solid fa-arrows-left-right',
            'color' => 'text-teal-600',
            'children' => [
                $node->start->accept($this),
                $node->end->accept($this),
            ],
        ];
    }

    /**
     * Visits a CharTypeNode and converts it into an array representation.
     *
     * Purpose: This method visualizes predefined character types like `\d` (digit) or `\s` (whitespace).
     * It provides a human-readable description of what the character type matches, enhancing clarity
     * in the UI.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a character type `\d`
     * $charTypeNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'CharType',
     * //   'label' => 'Character Type',
     * //   'detail' => '\d (Digit (0-9))',
     * //   'icon' => 'fa-solid fa-filter',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): array
    {
        $map = [
            'd' => 'Digit (0-9)', 'D' => 'Not Digit',
            'w' => 'Word Char', 'W' => 'Not Word Char',
            's' => 'Whitespace', 'S' => 'Not Whitespace',
        ];

        return [
            'type' => 'CharType',
            'label' => 'Character Type',
            'detail' => '\\'.$node->value.' ('.($map[$node->value] ?? 'Custom').')',
            'icon' => 'fa-solid fa-filter',
            'color' => 'text-blue-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a DotNode and converts it into an array representation.
     *
     * Purpose: This method visualizes the wildcard dot (`.`) character. It provides a simple
     * description indicating that it matches "any character" (with caveats depending on flags),
     * which is helpful for understanding its broad matching capability.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a dot `.`
     * $dotNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Dot',
     * //   'label' => 'Wildcard (Dot)',
     * //   'detail' => 'Any character',
     * //   'icon' => 'fa-solid fa-circle',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): array
    {
        return [
            'type' => 'Dot',
            'label' => 'Wildcard (Dot)',
            'detail' => 'Any character',
            'icon' => 'fa-solid fa-circle',
            'color' => 'text-pink-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits an AnchorNode and converts it into an array representation.
     *
     * Purpose: This method visualizes positional anchors like `^` (start of line) or `$` (end of line).
     * It provides a clear description of what position the anchor asserts, which is crucial for
     * understanding boundary matching.
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For an anchor `^`
     * $anchorNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Anchor',
     * //   'label' => 'Anchor',
     * //   'detail' => '^ (Start of Line)',
     * //   'icon' => 'fa-solid fa-anchor',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): array
    {
        $map = ['^' => 'Start of Line', '$' => 'End of Line', '\A' => 'Start of String', '\z' => 'End of String'];

        return [
            'type' => 'Anchor',
            'label' => 'Anchor',
            'detail' => $node->value.' ('.($map[$node->value] ?? 'Custom').')',
            'icon' => 'fa-solid fa-anchor',
            'color' => 'text-rose-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits an AssertionNode and converts it into an array representation.
     *
     * Purpose: This method visualizes zero-width assertions like `\b` (word boundary) or `\A` (start of subject).
     * It displays the assertion value, helping users understand conditions that must be met without consuming characters.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For an assertion `\b`
     * $assertionNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Assertion',
     * //   'label' => 'Assertion',
     * //   'detail' => '\b',
     * //   'icon' => 'fa-solid fa-check-double',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): array
    {
        return [
            'type' => 'Assertion',
            'label' => 'Assertion',
            'detail' => '\\'.$node->value,
            'icon' => 'fa-solid fa-check-double',
            'color' => 'text-amber-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a BackrefNode and converts it into an array representation.
     *
     * Purpose: This method visualizes backreferences to previously captured groups. It clearly
     * indicates which group is being referenced (by number or name), which is essential for
     * understanding patterns that match repeated text.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a backreference `\1`
     * $backrefNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Backref',
     * //   'label' => 'Backreference',
     * //   'detail' => 'To group: 1',
     * //   'icon' => 'fa-solid fa-clock-rotate-left',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): array
    {
        return [
            'type' => 'Backref',
            'label' => 'Backreference',
            'detail' => 'To group: '.$node->ref,
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a UnicodeNode and converts it into an array representation.
     *
     * Purpose: This method visualizes Unicode characters specified by their hexadecimal code points.
     * It displays the code, helping users understand the exact character being matched, especially
     * for non-ASCII characters.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a Unicode character `\x{2603}` (snowman)
     * $unicodeNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Unicode',
     * //   'label' => 'Unicode Character',
     * //   'detail' => '{2603}',
     * //   'icon' => 'fa-solid fa-language',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): array
    {
        return [
            'type' => 'Unicode',
            'label' => 'Unicode Character',
            'detail' => $node->code,
            'icon' => 'fa-solid fa-language',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): array
    {
        return [
            'type' => 'UnicodeNamed',
            'label' => 'Unicode Named Character',
            'detail' => $node->name,
            'icon' => 'fa-solid fa-language',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a UnicodePropNode and converts it into an array representation.
     *
     * Purpose: This method visualizes Unicode character properties (e.g., `\p{L}` for letters).
     * It displays the property name, allowing users to understand character matching based on
     * Unicode categories.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a Unicode property `\p{L}`
     * $unicodePropNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'UnicodeProp',
     * //   'label' => 'Unicode Property',
     * //   'detail' => '\p{L}',
     * //   'icon' => 'fa-solid fa-globe-europe',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): array
    {
        return [
            'type' => 'UnicodeProp',
            'label' => 'Unicode Property',
            'detail' => '\p{'.$node->prop.'}',
            'icon' => 'fa-solid fa-globe-europe',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits an OctalNode and converts it into an array representation.
     *
     * Purpose: This method visualizes modern octal character escapes (e.g., `\o{101}`).
     * It uses a generic leaf representation to display the octal code.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For an octal escape `\o{101}`
     * $octalNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Generic',
     * //   'label' => 'Octal',
     * //   'detail' => '101',
     * //   'icon' => 'fa-solid fa-cube',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): array
    {
        return $this->genericLeaf('Octal', $node->code);
    }

    /**
     * Visits an OctalLegacyNode and converts it into an array representation.
     *
     * Purpose: This method visualizes legacy octal character escapes (e.g., `\012`).
     * It uses a generic leaf representation to display the octal code, highlighting
     * its legacy nature.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a legacy octal escape `\012`
     * $octalLegacyNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Generic',
     * //   'label' => 'Legacy Octal',
     * //   'detail' => '012',
     * //   'icon' => 'fa-solid fa-cube',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): array
    {
        return $this->genericLeaf('Legacy Octal', $node->code);
    }

    /**
     * Visits a PosixClassNode and converts it into an array representation.
     *
     * Purpose: This method visualizes POSIX character classes (e.g., `[:alpha:]`).
     * It displays the class name, providing a clear representation of these predefined
     * character sets.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a POSIX class `[:digit:]`
     * $posixClassNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'PosixClass',
     * //   'label' => 'POSIX Class',
     * //   'detail' => '[:digit:]',
     * //   'icon' => 'fa-solid fa-box-archive',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): array
    {
        return [
            'type' => 'PosixClass',
            'label' => 'POSIX Class',
            'detail' => '[:'.$node->class.':]',
            'icon' => 'fa-solid fa-box-archive',
            'color' => 'text-slate-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a CommentNode and converts it into an array representation.
     *
     * Purpose: This method visualizes inline comments within the regex. While comments
     * don't affect matching, displaying them helps in understanding the original author's
     * intent and provides context for complex patterns.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a comment `(?# This is a comment)`
     * $commentNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Comment',
     * //   'label' => 'Comment',
     * //   'detail' => ' This is a comment',
     * //   'icon' => 'fa-solid fa-comment-slash',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): array
    {
        return [
            'type' => 'Comment',
            'label' => 'Comment',
            'detail' => $node->comment,
            'icon' => 'fa-solid fa-comment-slash',
            'color' => 'text-gray-400',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a ConditionalNode and converts it into an array representation.
     *
     * Purpose: This method visualizes conditional constructs (if-then-else logic) in a regex.
     * It clearly separates the condition, the "if true" branch, and the "if false" branch,
     * making complex branching patterns easier to understand.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, background color, and children (recursively
     *                              processed) for UI display
     *
     * @example
     * ```php
     * // For a conditional `(?(1)yes|no)`
     * $conditionalNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Conditional',
     * //   'label' => 'Conditional (If-Then-Else)',
     * //   'icon' => 'fa-solid fa-code-fork',
     * //   'children' => [
     * //     ['label' => 'Condition', 'children' => [...] /* BackrefNode for '1' *\/],
     * //     ['label' => 'If True', 'children' => [...] /* Node for 'yes' *\/],
     * //     ['label' => 'If False', 'children' => [...] /* Node for 'no' *\/],
     * //   ]
     * // ]
     * ```
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): array
    {
        return [
            'type' => 'Conditional',
            'label' => 'Conditional (If-Then-Else)',
            'icon' => 'fa-solid fa-code-fork',
            'color' => 'text-fuchsia-600',
            'bg' => 'bg-fuchsia-50',
            'children' => [
                ['label' => 'Condition', 'children' => [$node->condition->accept($this)]],
                ['label' => 'If True', 'children' => [$node->yes->accept($this)]],
                ['label' => 'If False', 'children' => [$node->no->accept($this)]],
            ],
        ];
    }

    /**
     * Visits a SubroutineNode and converts it into an array representation.
     *
     * Purpose: This method visualizes subroutine calls within the regex. It displays the
     * reference (e.g., group number or name) of the pattern being called, helping to
     * understand recursive or reused patterns.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a subroutine call `(?&my_pattern)`
     * $subroutineNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Subroutine',
     * //   'label' => 'Subroutine',
     * //   'detail' => 'Call: my_pattern',
     * //   'icon' => 'fa-solid fa-recycle',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): array
    {
        return [
            'type' => 'Subroutine',
            'label' => 'Subroutine',
            'detail' => 'Call: '.$node->reference,
            'icon' => 'fa-solid fa-recycle',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a PcreVerbNode and converts it into an array representation.
     *
     * Purpose: This method visualizes PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`).
     * It displays the verb, providing insight into how the regex engine's backtracking
     * behavior is being manipulated.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a PCRE verb `(*FAIL)`
     * $pcreVerbNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'PcreVerb',
     * //   'label' => 'Control Verb',
     * //   'detail' => '(*FAIL)',
     * //   'icon' => 'fa-solid fa-gamepad',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): array
    {
        return [
            'type' => 'PcreVerb',
            'label' => 'Control Verb',
            'detail' => '(*'.$node->verb.')',
            'icon' => 'fa-solid fa-gamepad',
            'color' => 'text-pink-500',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a DefineNode and converts it into an array representation.
     *
     * Purpose: This method visualizes the `(?(DEFINE)...)` block, which is used to define
     * named sub-patterns for later reuse. It processes the content of the define block,
     * helping to understand the library of patterns available.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return array<string, mixed> an associative array containing the type, label, icon,
     *                              color, and children (recursively processed) for UI display
     *
     * @example
     * ```php
     * // For a DEFINE block `(?(DEFINE)(?<digit>\d))`
     * $defineNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Define',
     * //   'label' => '(DEFINE) Block',
     * //   'icon' => 'fa-solid fa-book',
     * //   'children' => [...] // Node for the defined group `(?<digit>\d)`
     * // ]
     * ```
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): array
    {
        return [
            'type' => 'Define',
            'label' => '(DEFINE) Block',
            'icon' => 'fa-solid fa-book',
            'color' => 'text-slate-500',
            'children' => [$node->content->accept($this)],
        ];
    }

    /**
     * Visits a KeepNode and converts it into an array representation.
     *
     * Purpose: This method visualizes the `\K` "keep" assertion. It indicates that the
     * match start position is reset at this point, which is important for understanding
     * how the final matched string is determined.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     *
     * @example
     * ```php
     * // For a keep assertion `\K`
     * $keepNode->accept($visitor);
     * // Returns:
     * // [
     * //   'type' => 'Keep',
     * //   'label' => 'Keep (\K)',
     * //   'detail' => 'Reset match start',
     * //   'icon' => 'fa-solid fa-scissors',
     * //   'isLeaf' => true,
     * // ]
     * ```
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): array
    {
        return [
            'type' => 'Keep',
            'label' => 'Keep (\K)',
            'detail' => 'Reset match start',
            'icon' => 'fa-solid fa-scissors',
            'color' => 'text-orange-500',
            'isLeaf' => true,
        ];
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): array
    {
        return [
            'type' => 'LimitMatch',
            'label' => 'Match Limit',
            'detail' => '(*LIMIT_MATCH='.$node->limit.')',
            'icon' => 'fa-solid fa-gauge-high',
            'color' => 'text-red-500',
            'isLeaf' => true,
        ];
    }

    /**
     * Visits a CalloutNode and converts it into an array representation.
     *
     * Callouts trigger user-defined code without consuming characters, so they are
     * represented as leaf nodes with their identifier.
     */
    #[\Override]
    public function visitCallout(Node\CalloutNode $node): array
    {
        $detail = match (true) {
            \is_int($node->identifier) => '(?C'.$node->identifier.')',
            $node->isStringIdentifier => '(?C"'.$node->identifier.'")',
            default => '(?C'.$node->identifier.')',
        };

        return [
            'type' => 'Callout',
            'label' => 'Callout',
            'detail' => $detail,
            'icon' => 'fa-solid fa-plug',
            'color' => 'text-amber-600',
            'isLeaf' => true,
        ];
    }

    /**
     * Creates a generic array representation for simple leaf nodes.
     *
     * Purpose: This helper method provides a consistent structure for nodes that do not
     * have children and can be represented simply by a label and a detail string. It
     * reduces redundancy in the visitor methods for basic elements.
     *
     * @param string $label  The primary label for the node (e.g., "Octal", "Legacy Octal").
     * @param string $detail A more specific detail about the node (e.g., the octal code).
     *
     * @return array<string, mixed> an associative array containing the type, label, detail,
     *                              icon, color, and a `isLeaf` flag for UI display
     */
    private function genericLeaf(string $label, string $detail): array
    {
        return [
            'type' => 'Generic',
            'label' => $label,
            'detail' => $detail,
            'icon' => 'fa-solid fa-cube',
            'color' => 'text-gray-500',
            'isLeaf' => true,
        ];
    }

    /**
     * Formats a string value for display, escaping common control characters.
     *
     * Purpose: This private helper ensures that literal values, especially those containing
     * non-printable characters like newlines or tabs, are displayed in a readable format
     * (e.g., `\n` instead of an actual newline character) within the UI.
     *
     * @param string $value the raw string value to format
     *
     * @return string the formatted string, enclosed in double quotes and with control
     *                characters escaped
     */
    private function formatValue(string $value): string
    {
        $map = ["\n" => '\n', "\r" => '\r', "\t" => '\t'];

        return '"'.strtr($value, $map).'"';
    }
}
