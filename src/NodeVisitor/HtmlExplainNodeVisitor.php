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
use RegexParser\Node\QuantifierType;

/**
 * A visitor that explains the AST in an HTML format for rich display.
 *
 * Purpose: This visitor traverses the Abstract Syntax Tree (AST) of a regular expression
 * and generates a human-readable explanation of its components in HTML format.
 * This is particularly useful for visualizing complex regex patterns, making them
 * easier to understand for developers and non-technical users alike. It breaks down
 * the regex into its logical parts and describes what each part matches.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class HtmlExplainNodeVisitor extends AbstractNodeVisitor
{
    private const array CHAR_TYPE_MAP = [
        'd' => 'any digit (0-9)',
        'D' => 'any non-digit',
        's' => 'any whitespace character',
        'S' => 'any non-whitespace character',
        'w' => 'any "word" character (alphanumeric or _)',
        'W' => 'any "non-word" character',
        'h' => 'any horizontal whitespace',
        'H' => 'any non-horizontal whitespace',
        'v' => 'any vertical whitespace',
        'V' => 'any non-vertical whitespace',
        'R' => 'a generic newline (\\r\\n, \\r, or \\n)',
    ];

    private const array ANCHOR_MAP = [
        '^' => 'the start of the string (or line, with /m flag)',
        '$' => 'the end of the string (or line, with /m flag)',
    ];

    private const array ASSERTION_MAP = [
        'A' => 'the absolute start of the string',
        'z' => 'the absolute end of the string',
        'Z' => 'the end of the string (before final newline)',
        'G' => 'the position of the last successful match',
        'b' => 'a word boundary',
        'B' => 'a non-word boundary',
    ];

    /**
     * Visits a RegexNode and generates an HTML explanation for the entire regex.
     *
     * Purpose: This is the entry point for generating an HTML explanation of a regular expression.
     * It wraps the explanation of the main pattern with overall regex context, including any flags,
     * providing a structured and comprehensive overview.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return string an HTML string explaining the regex pattern and its flags
     *
     * @example
     * ```php
     * // Assuming $regexNode is the root of your parsed AST for '/hello/i'
     * $visitor = new HtmlExplainNodeVisitor();
     * $html = $regexNode->accept($visitor);
     * // $html will contain a div with the explanation of "hello" and mention of the 'i' flag.
     * ```
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        $patternExplain = $node->pattern->accept($this);
        $flags = $node->flags ? $this->e(' (with flags: '.$node->flags.')') : '';

        return \sprintf(
            "<div class=\"regex-explain\">\n<strong>Regex matches%s:</strong>\n<ul>%s</ul>\n</div>",
            $flags,
            $patternExplain,
        );
    }

    /**
     * Visits an AlternationNode and generates an HTML explanation for its alternatives.
     *
     * Purpose: This method explains the "OR" logic in a regex (e.g., `cat|dog`).
     * It clearly separates each alternative in the HTML output, making it easy to
     * understand that the regex engine will try to match one of the provided options.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return string an HTML string explaining the alternation, with each alternative listed
     *
     * @example
     * ```php
     * // For an alternation like `(apple|banana|orange)`
     * $alternationNode->accept($visitor);
     * // Returns HTML like:
     * // <li><strong>EITHER:</strong><ul>...explanation of apple...</ul></li>
     * // <li><strong>OR:</strong><ul>...explanation of banana...</ul></li>
     * // <li><strong>OR:</strong><ul>...explanation of orange...</ul></li>
     * ```
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        $alts = array_map(
            fn (Node\NodeInterface $alt) => $alt->accept($this),
            $node->alternatives,
        );

        return \sprintf(
            "<li><strong>EITHER:</strong>\n<ul>%s</ul>\n</li>",
            implode("\n<li><strong>OR:</strong>\n<ul>", $alts),
        );
    }

    /**
     * Visits a SequenceNode and generates an HTML explanation for its child elements.
     *
     * Purpose: This method explains a linear sequence of regex elements (e.g., `abc`).
     * It concatenates the HTML explanations of all child nodes, preserving their order,
     * to show that these elements must match consecutively.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return string an HTML string concatenating the explanations of its child nodes
     *
     * @example
     * ```php
     * // For a sequence `foo` (represented as a sequence of 'f', 'o', 'o' literals)
     * $sequenceNode->accept($visitor);
     * // Returns HTML like:
     * // <li>Literal: <strong>'f'</strong></li>
     * // <li>Literal: <strong>'o'</strong></li>
     * // <li>Literal: <strong>'o'</strong></li>
     * ```
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);
        $parts = array_filter($parts, fn ($part) => '' !== $part);

        return implode("\n", $parts);
    }

    /**
     * Visits a GroupNode and generates an HTML explanation for the group and its content.
     *
     * Purpose: This method provides detailed explanations for various types of groups
     * (capturing, non-capturing, named, lookarounds, atomic, etc.). It clearly labels
     * the group's purpose and then recursively explains its internal pattern, helping
     * users understand the role of each grouping construct.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return string an HTML string explaining the group's type and its child's pattern
     *
     * @example
     * ```php
     * // For a capturing group `(abc)`
     * $groupNode->accept($visitor);
     * // Returns HTML like:
     * // <li><span title="Start Capturing Group"><strong>Start Capturing Group:</strong></span>
     * // <ul><li>...explanation of abc...</li></ul></li>
     *
     * // For a positive lookahead `(?=test)`
     * $groupNode->accept($visitor);
     * // Returns HTML like:
     * // <li><span title="Start Positive Lookahead"><strong>Start Positive Lookahead:</strong></span>
     * // <ul><li>...explanation of test...</li></ul></li>
     * ```
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): string
    {
        $childExplain = $node->child->accept($this);
        $type = match ($node->type) {
            GroupType::T_GROUP_CAPTURING => 'Start Capturing Group',
            GroupType::T_GROUP_NON_CAPTURING => 'Start Non-Capturing Group',
            GroupType::T_GROUP_NAMED => \sprintf("Start Capturing Group (named: '%s')", $this->e($node->name)),
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => 'Start Positive Lookahead',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => 'Start Negative Lookahead',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => 'Start Positive Lookbehind',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => 'Start Negative Lookbehind',
            GroupType::T_GROUP_ATOMIC => 'Start Atomic Group',
            GroupType::T_GROUP_BRANCH_RESET => 'Start Branch Reset Group',
            GroupType::T_GROUP_INLINE_FLAGS => \sprintf("Start Group (with flags: '%s')", $this->e($node->flags)),
        };

        return \sprintf(
            "<li><span title=\"%s\"><strong>%s:</strong></span>\n<ul>%s</ul>\n</li>",
            $this->e($type),
            $this->e($type),
            $childExplain,
        );
    }

    /**
     * Visits a QuantifierNode and generates an HTML explanation for the repetition.
     *
     * Purpose: This method explains how many times a preceding element is allowed to repeat
     * (e.g., `*`, `+`, `{1,5}`). It also clarifies the "greediness" type (greedy, lazy, possessive).
     * The explanation is integrated with the quantified element's explanation for clarity.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return string an HTML string explaining the quantifier and its quantified child
     *
     * @example
     * ```php
     * // For a quantifier `a+?`
     * $quantifierNode->accept($visitor);
     * // Returns HTML like:
     * // <li>(one or more times (as few as possible)) Literal: <strong>'a'</strong></li>
     *
     * // For a complex quantified group `(foo){2,5}`
     * $quantifierNode->accept($visitor);
     * // Returns HTML like:
     * // <li><strong>Quantifier (between 2 and 5 times):</strong>
     * // <ul><li>...explanation of foo...</li></ul></li>
     * ```
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $childExplain = $node->node->accept($this);
        $quantExplain = $this->explainQuantifierValue($node->quantifier, $node->type);

        // If the child is simple (one line <li>), put it on one line.
        if (str_starts_with((string) $childExplain, '<li>') && !str_contains(substr((string) $childExplain, 4), '<li>')) {
            // Inject the quantifier explanation into the child's <li>
            return str_replace('<li>', \sprintf('<li>(%s) ', $this->e($quantExplain)), (string) $childExplain);
        }

        // If the child is complex, wrap it
        return \sprintf(
            "<li><strong>Quantifier (%s):</strong>\n<ul>%s</ul>\n</li>",
            $this->e($quantExplain),
            $childExplain,
        );
    }

    /**
     * Visits a LiteralNode and generates an HTML explanation for the literal character(s).
     *
     * Purpose: This method explains literal characters or strings (e.g., `a`, `hello`).
     * It formats the literal value for display, including handling special characters
     * like newlines (`\n`) for better readability in the HTML output.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return string an HTML string explaining the literal value
     *
     * @example
     * ```php
     * // For a literal `a`
     * $literalNode->accept($visitor); // Returns HTML like: <li>Literal: <strong>'a'</strong></li>
     *
     * // For a literal newline `\n`
     * $literalNode->accept($visitor); // Returns HTML like: <li>Literal: <strong>'\n' (newline)</strong></li>
     * ```
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): string
    {
        $explanation = $this->explainLiteral($node->value);

        return \sprintf(
            '<li><span title="Literal: %s">Literal: <strong>%s</strong></span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    /**
     * Visits a CharTypeNode and generates an HTML explanation for the character type.
     *
     * Purpose: This method explains predefined character types (e.g., `\d` for digit, `\s` for whitespace).
     * It provides a human-readable description of what the character type matches, enhancing clarity
     * in the HTML output.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return string an HTML string explaining the character type
     *
     * @example
     * ```php
     * // For a character type `\d`
     * $charTypeNode->accept($visitor);
     * // Returns HTML like: <li>Character Type: <strong>\d</strong> (any digit (0-9))</li>
     * ```
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        $explanation = self::CHAR_TYPE_MAP[$node->value] ?? 'unknown (\\'.$node->value.')';

        return \sprintf(
            '<li><span title="Character Type: %s">Character Type: <strong>\%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    /**
     * Visits a DotNode and generates an HTML explanation for the wildcard dot.
     *
     * Purpose: This method explains the wildcard dot (`.`) character. It provides a simple
     * description indicating that it matches "any character" (with caveats depending on flags),
     * which is helpful for understanding its broad matching capability in the HTML output.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return string an HTML string explaining the wildcard dot
     *
     * @example
     * ```php
     * // For a dot `.`
     * $dotNode->accept($visitor);
     * // Returns HTML like: <li>Wildcard: <strong>.</strong> (any character (except newline, unless /s flag is used))</li>
     * ```
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): string
    {
        $explanation = 'any character (except newline, unless /s flag is used)';

        return \sprintf(
            '<li><span title="%s">Wildcard: <strong>.</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    /**
     * Visits an AnchorNode and generates an HTML explanation for the positional anchor.
     *
     * Purpose: This method explains positional anchors like `^` (start of line) or `$` (end of line).
     * It provides a clear description of what position the anchor asserts, which is crucial for
     * understanding boundary matching in the HTML output.
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return string an HTML string explaining the anchor
     *
     * @example
     * ```php
     * // For an anchor `^`
     * $anchorNode->accept($visitor);
     * // Returns HTML like: <li>Anchor: <strong>^</strong> (the start of the string (or line, with /m flag))</li>
     * ```
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): string
    {
        $explanation = self::ANCHOR_MAP[$node->value] ?? $node->value;

        return \sprintf(
            '<li><span title="%s">Anchor: <strong>%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    /**
     * Visits an AssertionNode and generates an HTML explanation for the zero-width assertion.
     *
     * Purpose: This method explains zero-width assertions like `\b` (word boundary) or `\A` (start of subject).
     * It displays the assertion value and its meaning, helping users understand conditions that must be met
     * without consuming characters, presented clearly in HTML.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return string an HTML string explaining the assertion
     *
     * @example
     * ```php
     * // For an assertion `\b`
     * $assertionNode->accept($visitor);
     * // Returns HTML like: <li>Assertion: <strong>\b</strong> (a word boundary)</li>
     * ```
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): string
    {
        $explanation = self::ASSERTION_MAP[$node->value] ?? '\\'.$node->value;

        return \sprintf(
            '<li><span title="%s">Assertion: <strong>\%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    /**
     * Visits a KeepNode and generates an HTML explanation for the `\K` assertion.
     *
     * Purpose: This method explains the `\K` "keep" assertion. It indicates that the
     * match start position is reset at this point, which is important for understanding
     * how the final matched string is determined. The explanation is provided in HTML.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return string an HTML string explaining the `\K` assertion
     *
     * @example
     * ```php
     * // For a keep assertion `\K`
     * $keepNode->accept($visitor);
     * // Returns HTML like: <li>Assertion: <strong>\K</strong> (\K (reset match start))</li>
     * ```
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): string
    {
        $explanation = '\K (reset match start)';

        return \sprintf(
            '<li><span title="%s">Assertion: <strong>\K</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    /**
     * Visits a CharClassNode and generates an HTML explanation for the character set.
     *
     * Purpose: This method explains character sets (e.g., `[a-z]`, `[^0-9]`). It determines
     * if the class is negated and lists its constituent parts, providing a clear HTML
     * representation of the characters that are (or are not) matched.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return string an HTML string explaining the character class
     *
     * @example
     * ```php
     * // For a character class `[a-zA-Z]`
     * $charClassNode->accept($visitor);
     * // Returns HTML like: <li><span title="Character Class: any character in [ 'a', 'Z' ]">Character Class: [ 'a', 'Z' ]</span></li>
     *
     * // For a negated character class `[^0-9]`
     * $charClassNode->accept($visitor);
     * // Returns HTML like: <li><span title="Character Class: any character NOT in [ '0', '9' ]">Character Class: [ <strong>NOT</strong> '0', '9' ]</span></li>
     * ```
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $neg = $node->isNegated ? '<strong>NOT</strong> ' : '';
        $expressionParts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];
        $explainedParts = array_map(fn (Node\NodeInterface $part) => $part->accept($this), $expressionParts);

        // Char class parts are just strings, not <li>
        $parts = array_map(strip_tags(...), $explainedParts);

        $explanation = \sprintf('any character %sin [ %s ]', $neg, implode(', ', $parts));

        return \sprintf(
            '<li><span title="%s">Character Class: [ %s%s ]</span></li>',
            $this->e(strip_tags($explanation)),
            $neg,
            $this->e(implode(', ', $parts)),
        );
    }

    /**
     * Visits a RangeNode and generates an HTML explanation for the character range.
     *
     * Purpose: This method explains character ranges within a character class (e.g., `a-z`).
     * It processes the start and end characters of the range, providing a clear HTML
     * representation of the inclusive character set.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return string an HTML string explaining the character range
     *
     * @example
     * ```php
     * // For a range `a-z` inside a character class
     * $rangeNode->accept($visitor); // Returns HTML like: Range: from 'a' to 'z'
     * ```
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): string
    {
        $start = ($node->start instanceof Node\LiteralNode)
            ? $this->explainLiteral($node->start->value)
            : $node->start->accept($this);

        $end = ($node->end instanceof Node\LiteralNode)
            ? $this->explainLiteral($node->end->value)
            : $node->end->accept($this);

        return \sprintf('Range: from %s to %s', $this->e((string) $start), $this->e((string) $end));
    }

    /**
     * Visits a BackrefNode and generates an HTML explanation for the backreference.
     *
     * Purpose: This method explains backreferences to previously captured groups. It clearly
     * indicates which group is being referenced (by number or name), which is essential for
     * understanding patterns that match repeated text, presented in HTML.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return string an HTML string explaining the backreference
     *
     * @example
     * ```php
     * // For a backreference `\1`
     * $backrefNode->accept($visitor);
     * // Returns HTML like: <li><span title="matches text from group "1"">Backreference: <strong>\1</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): string
    {
        $explanation = \sprintf('matches text from group "%s"', $node->ref);

        return \sprintf(
            '<li><span title="%s">Backreference: <strong>\%s</strong></span></li>',
            $this->e($explanation),
            $this->e($node->ref),
        );
    }

    /**
     * Visits a UnicodeNode and generates an HTML explanation for the Unicode character.
     *
     * Purpose: This method explains Unicode characters specified by their hexadecimal code points.
     * It displays the code, helping users understand the exact character being matched, especially
     * for non-ASCII characters, presented clearly in HTML.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return string an HTML string explaining the Unicode character
     *
     * @example
     * ```php
     * // For a Unicode character `\x{2603}` (snowman)
     * $unicodeNode->accept($visitor);
     * // Returns HTML like: <li><span title="Unicode: {2603}">Unicode: <strong>{2603}</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        return \sprintf(
            '<li><span title="Unicode: %s">Unicode: <strong>%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): string
    {
        return \sprintf(
            '<li><span title="Unicode named: %s">Unicode named: <strong>%s</strong></span></li>',
            $this->e($node->name),
            $this->e($node->name),
        );
    }

    /**
     * Visits a UnicodePropNode and generates an HTML explanation for the Unicode property.
     *
     * Purpose: This method explains Unicode character properties (e.g., `\p{L}` for letters).
     * It displays the property name and whether it's matching or non-matching, allowing users
     * to understand character matching based on Unicode categories, presented in HTML.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return string an HTML string explaining the Unicode property
     *
     * @example
     * ```php
     * // For a Unicode property `\p{L}`
     * $unicodePropNode->accept($visitor);
     * // Returns HTML like: <li><span title="any character matching "L"">Unicode Property: <strong>\p{L}</strong></span></li>
     *
     * // For a negated Unicode property `\P{N}`
     * $unicodePropNode->accept($visitor);
     * // Returns HTML like: <li><span title="any character non-matching "N"">Unicode Property: <strong>\P{N}</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $type = str_starts_with($node->prop, '^') ? 'non-matching' : 'matching';
        $prop = ltrim($node->prop, '^');
        $explanation = \sprintf('any character %s "%s"', $type, $prop);
        $prefix = str_starts_with($node->prop, '^') ? 'P' : 'p';

        return \sprintf(
            '<li><span title="%s">Unicode Property: <strong>\%s{%s}</strong></span></li>',
            $this->e($explanation),
            $prefix,
            $this->e($prop),
        );
    }

    /**
     * Visits an OctalNode and generates an HTML explanation for the octal character escape.
     *
     * Purpose: This method explains modern octal character escapes (e.g., `\o{101}`).
     * It displays the octal code, helping users understand the exact character being matched,
     * presented clearly in HTML.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return string an HTML string explaining the octal character escape
     *
     * @example
     * ```php
     * // For an octal escape `\o{101}`
     * $octalNode->accept($visitor);
     * // Returns HTML like: <li><span title="Octal: 101">Octal: <strong>101</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): string
    {
        return \sprintf(
            '<li><span title="Octal: %s">Octal: <strong>%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    /**
     * Visits an OctalLegacyNode and generates an HTML explanation for the legacy octal character escape.
     *
     * Purpose: This method explains legacy octal character escapes (e.g., `\012`).
     * It displays the octal code, highlighting its legacy nature, and helping users understand
     * the exact character being matched, presented clearly in HTML.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return string an HTML string explaining the legacy octal character escape
     *
     * @example
     * ```php
     * // For a legacy octal escape `\012`
     * $octalLegacyNode->accept($visitor);
     * // Returns HTML like: <li><span title="Legacy Octal: 012">Legacy Octal: <strong>\012</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return \sprintf(
            '<li><span title="Legacy Octal: %s">Legacy Octal: <strong>\%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    /**
     * Visits a PosixClassNode and generates an HTML explanation for the POSIX character class.
     *
     * Purpose: This method explains POSIX character classes (e.g., `[:alpha:]`).
     * It displays the class name, providing a clear HTML representation of these predefined
     * character sets.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return string an HTML string explaining the POSIX character class
     *
     * @example
     * ```php
     * // For a POSIX class `[:digit:]`
     * $posixClassNode->accept($visitor); // Returns HTML like: POSIX Class: [[:digit:]]
     * ```
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return \sprintf('<li>POSIX Class: [[:%s:]]</li>', $this->e($node->class));
    }

    /**
     * Visits a CommentNode and generates an HTML explanation for the inline comment.
     *
     * Purpose: This method explains inline comments within the regex. While comments
     * don't affect matching, displaying them helps in understanding the original author's
     * intent and provides context for complex patterns, presented in HTML.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return string an HTML string explaining the comment
     *
     * @example
     * ```php
     * // For a comment `(?# This is a comment)`
     * $commentNode->accept($visitor);
     * // Returns HTML like: <li><span title="Comment" style="color: #888; font-style: italic;">Comment: This is a comment</span></li>
     * ```
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
        return \sprintf(
            '<li><span title="Comment" style="color: #888; font-style: italic;">Comment: %s</span></li>',
            $this->e($node->comment),
        );
    }

    /**
     * Visits a ConditionalNode and generates an HTML explanation for the conditional construct.
     *
     * Purpose: This method explains conditional constructs (if-then-else logic) in a regex.
     * It clearly separates the condition, the "if true" branch, and the "if false" branch
     * (if present), making complex branching patterns easier to understand in HTML.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return string an HTML string explaining the conditional construct
     *
     * @example
     * ```php
     * // For a conditional `(?(1)yes|no)`
     * $conditionalNode->accept($visitor);
     * // Returns HTML like:
     * // <li><strong>Conditional: IF</strong> (explanation of condition) <strong>THEN:</strong>
     * // <ul>...explanation of yes...</ul>
     * // <strong>ELSE:</strong>
     * // <ul>...explanation of no...</ul></li>
     *
     * // For a conditional `(?(DEFINE)pattern)`
     * $conditionalNode->accept($visitor);
     * // Returns HTML like:
     * // <li><strong>Conditional: IF</strong> (explanation of DEFINE) <strong>THEN:</strong>
     * // <ul>...explanation of pattern...</ul></li>
     * ```
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): string
    {
        $cond = $node->condition->accept($this);
        $yes = $node->yes->accept($this);

        // Check if the 'no' branch is an empty literal node
        $hasElseBranch = !($node->no instanceof Node\LiteralNode && '' === $node->no->value);
        $no = $hasElseBranch ? $node->no->accept($this) : '';

        // Condition node will be a <li>, just need its text
        $condText = trim(strip_tags((string) $cond));

        if ('' === $no || '<li></li>' === $no) {
            return \sprintf(
                "<li><strong>Conditional: IF</strong> (%s) <strong>THEN:</strong>\n<ul>%s</ul>\n</li>",
                $this->e($condText),
                $yes,
            );
        }

        return \sprintf(
            "<li><strong>Conditional: IF</strong> (%s) <strong>THEN:</strong>\n<ul>%s</ul>\n<strong>ELSE:</strong>\n<ul>%s</ul>\n</li>",
            $this->e($condText),
            $yes,
            $no,
        );
    }

    /**
     * Visits a SubroutineNode and generates an HTML explanation for the subroutine call.
     *
     * Purpose: This method explains subroutine calls within the regex. It displays the
     * reference (e.g., group number or name) of the pattern being called, helping to
     * understand recursive or reused patterns, presented in HTML.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return string an HTML string explaining the subroutine call
     *
     * @example
     * ```php
     * // For a subroutine call `(?&my_pattern)`
     * $subroutineNode->accept($visitor);
     * // Returns HTML like: <li><span title="recurses to group my_pattern">Subroutine Call: <strong>(?&my_pattern)</strong></span></li>
     *
     * // For a recursive call to the entire pattern `(?R)`
     * $subroutineNode->accept($visitor);
     * // Returns HTML like: <li><span title="recurses to the entire pattern">Subroutine Call: <strong>(?R)</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        $ref = match ($node->reference) {
            'R', '0' => 'the entire pattern',
            default => 'group '.$this->e($node->reference),
        };
        $explanation = \sprintf('recurses to %s', $ref);

        return \sprintf(
            '<li><span title="%s">Subroutine Call: <strong>(%s%s)</strong></span></li>',
            $this->e($explanation),
            $this->e($node->syntax),
            $this->e($node->reference),
        );
    }

    /**
     * Visits a PcreVerbNode and generates an HTML explanation for the PCRE control verb.
     *
     * Purpose: This method explains PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`).
     * It displays the verb, providing insight into how the regex engine's backtracking
     * behavior is being manipulated, presented in HTML.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return string an HTML string explaining the PCRE verb
     *
     * @example
     * ```php
     * // For a PCRE verb `(*FAIL)`
     * $pcreVerbNode->accept($visitor);
     * // Returns HTML like: <li><span title="PCRE Verb">PCRE Verb: <strong>(*FAIL)</strong></span></li>
     * ```
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return \sprintf(
            '<li><span title="PCRE Verb">PCRE Verb: <strong>(*%s)</strong></span></li>',
            $this->e($node->verb),
        );
    }

    /**
     * Visits a DefineNode and generates an HTML explanation for the `(?(DEFINE)...)` block.
     *
     * Purpose: This method explains the `(?(DEFINE)...)` block, which is used to define
     * named sub-patterns for later reuse. It processes the content of the define block
     * and explains that these patterns are defined without matching, helping to understand
     * the library of patterns available, presented in HTML.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return string an HTML string explaining the DEFINE block and its content
     *
     * @example
     * ```php
     * // For a DEFINE block `(?(DEFINE)(?<digit>\d))`
     * $defineNode->accept($visitor);
     * // Returns HTML like:
     * // <li><strong>DEFINE Block</strong> (defines subpatterns without matching):
     * // <ul><li>...explanation of (?<digit>\d)...</li></ul></li>
     * ```
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): string
    {
        $content = $node->content->accept($this);

        return \sprintf(
            "<li><strong>DEFINE Block</strong> (defines subpatterns without matching):\n<ul>%s</ul>\n</li>",
            $content,
        );
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        $explanation = \sprintf('sets the match limit to %d', $node->limit);

        return \sprintf(
            '<li><span title="%s">PCRE Verb: <strong>(*LIMIT_MATCH=%d)</strong></span></li>',
            $this->e($explanation),
            $node->limit,
        );
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): string
    {
        $argument = $node->isStringIdentifier ? '"'.$node->identifier.'"' : (string) $node->identifier;
        $explanation = \sprintf('passes control to user function with argument %s', $argument);

        return \sprintf(
            '<li><span title="%s">Callout: <strong>(?C%s)</strong></span></li>',
            $this->e($explanation),
            $this->e($argument),
        );
    }

    /**
     * Generates a human-readable description for a quantifier's value and type.
     *
     * Purpose: This private helper method centralizes the logic for translating
     * raw quantifier strings (e.g., `*`, `{1,5}`) and their types (greedy, lazy, possessive)
     * into clear, descriptive phrases suitable for the HTML explanation.
     *
     * @param string         $q    The raw quantifier string (e.g., `*`, `+`, `{1,5}`).
     * @param QuantifierType $type the type of quantifier (greedy, lazy, possessive)
     *
     * @return string a human-readable description of the quantifier
     */
    private function explainQuantifierValue(string $q, QuantifierType $type): string
    {
        $desc = match ($q) {
            '*' => 'zero or more times',
            '+' => 'one or more times',
            '?' => 'zero or one time',
            default => preg_match('/^\{(\d++)(?:,(\d*+))?\}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    \sprintf('at least %d times', $m[1]) :
                    \sprintf('between %d and %d times', $m[1], $m[2])
                ) :
                    \sprintf('exactly %d times', $m[1])
                ) :
                'with quantifier '.$q, // Fallback
        };

        $desc .= match ($type) {
            QuantifierType::T_LAZY => ' (as few as possible)',
            QuantifierType::T_POSSESSIVE => ' (and do not backtrack)',
            default => '',
        };

        return $desc;
    }

    /**
     * Generates a human-readable description for a literal character.
     *
     * Purpose: This private helper method provides clear descriptions for literal characters,
     * especially handling common control characters (like `\n`, `\t`) by translating them
     * into their escaped form and a descriptive name, or indicating non-printable characters.
     *
     * @param string $value the raw literal character string
     *
     * @return string a human-readable description of the literal character
     */
    private function explainLiteral(string $value): string
    {
        return match ($value) {
            ' ' => "' ' (space)",
            "\t" => "'\\t' (tab)",
            "\n" => "'\\n' (newline)",
            "\r" => "'\\r' (carriage return)",
            default => ctype_print($value) ? "'".$value."'" : '(non-printable char)',
        };
    }

    /**
     * Helper for HTML escaping.
     *
     * Purpose: This private utility method ensures that any string content inserted
     * into the HTML output is properly escaped to prevent XSS vulnerabilities and
     * ensure correct rendering of special characters.
     *
     * @param string|null $s the string to be HTML escaped
     *
     * @return string the HTML escaped string
     */
    private function e(?string $s): string
    {
        return htmlspecialchars((string) $s, \ENT_QUOTES, 'UTF-8');
    }
}
