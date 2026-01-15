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

namespace RegexParser\Exception;

/**
 * Trait for building helpful error messages with suggestions.
 *
 * This trait provides methods to construct error messages that not only
 * describe the problem but also explain how to fix it, similar to
 * how modern IDEs provide fix suggestions.
 */
trait HelpfulExceptionTrait
{
    /**
     * Build a helpful error message with problem description and fix suggestion.
     *
     * @param string $problem    Description of what went wrong
     * @param string $suggestion Suggested fix or solution
     * @param string|null $docsLink  Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildSuggestion(
        string $problem,
        string $suggestion,
        ?string $docsLink = null,
    ): string {
        $message = "Problem: {$problem}\n";
        $message .= "\nFix: {$suggestion}\n";

        if ($docsLink !== null) {
            $message .= "\nLearn more: {$docsLink}\n";
        }

        return $message;
    }

    /**
     * Build a helpful error message with code examples.
     *
     * @param string $problem     Description of what went wrong
     * @param string $before      Problematic code example
     * @param string $after       Corrected code example
     * @param string|null $explanation Optional explanation of why the fix works
     * @param string|null $docsLink    Optional link to documentation
     * @return string Formatted help message with code examples
     */
    private static function buildCodeExampleSuggestion(
        string $problem,
        string $before,
        string $after,
        ?string $explanation = null,
        ?string $docsLink = null,
    ): string {
        $message = "Problem: {$problem}\n";
        $message .= "\nBefore:\n  {$before}\n";
        $message .= "\nAfter:\n  {$after}\n";

        if ($explanation !== null) {
            $message .= "\nWhy: {$explanation}\n";
        }

        if ($docsLink !== null) {
            $message .= "\nLearn more: {$docsLink}\n";
        }

        return $message;
    }

    /**
     * Build a helpful error message for quantifier issues.
     *
     * @param string $position    Position where quantifier appears
     * @param string $quantifier The quantifier (+, *, ?, {n,m})
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildQuantifierSuggestion(
        string $position,
        string $quantifier,
        ?string $docsLink = null,
    ): string {
        $problem = "Quantifier '{$quantifier}' without target at {$position}";
        $suggestion = "Remove quantifier or add an atom before it";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/concepts/quantifiers';
        }

        return self::buildSuggestion($problem, $suggestion, $docsLink);
    }

    /**
     * Build a helpful error message for invalid escape sequences.
     *
     * @param string $escape      The invalid escape sequence
     * @param string $position    Where it was found
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildEscapeSuggestion(
        string $escape,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problem = "Invalid escape sequence '{$escape}' at {$position}";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/concepts/escape-sequences';
        }

        $suggestion = self::getEscapeFix($escape);

        return self::buildSuggestion($problem, $suggestion, $docsLink);
    }

    /**
     * Get appropriate fix for an invalid escape sequence.
     *
     * @param string $escape The invalid escape sequence
     * @return string Suggested fix
     */
    private static function getEscapeFix(string $escape): string
    {
        $fixes = [
            '\c' => 'Use \\cX for control characters where X is a letter (A-Z)',
            '\x' => 'Use \\x{NN} or \\xNN for hexadecimal codes',
            '\u' => 'Use \\u{NNNN} or \\uNNNN for Unicode code points',
            '\N{' => 'Use \\N{U+XXXX} for Unicode named characters',
        ];

        return $fixes[$escape] ?? 'Remove the backslash or check PCRE version support';
    }

    /**
     * Build a helpful error message for character class issues.
     *
     * @param string $className     Name of character class issue
     * @param string $position      Where it was found
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildCharacterClassSuggestion(
        string $className,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problems = [
            'suspiciousAsciiRange' => [
                'problem' => 'Suspicious ASCII range [A-z] contains non-letter characters',
                'suggestion' => 'Use [A-Za-z] for case-insensitive letters or [a-z] with /i flag',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/concepts/character-classes#ascii-ranges',
            ],
            'redundantCharacterClass' => [
                'problem' => 'Redundant character class detected',
                'suggestion' => 'Use literal character or escape special characters',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/lint/redundant-character-class',
            ],
            'invalidRange' => [
                'problem' => 'Invalid character class range at position ' . $position,
                'suggestion' => 'Ensure range start code point is less than or equal to end code point',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/concepts/character-classes#ranges',
            ],
        ];

        if (!array_key_exists($className, $problems)) {
            return "Invalid character class: {$className} at {$position}";
        }

        $info = $problems[$className];

        return self::buildSuggestion(
            $info['problem'],
            $info['suggestion'],
            $info['docs'],
        );
    }

    /**
     * Build a helpful error message for backreference issues.
     *
     * @param string $referenceNumber  The backreference number
     * @param string $position        Where it was found
     * @param int|null $groupCount    Total number of capturing groups
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildBackreferenceSuggestion(
        string $referenceNumber,
        string $position,
        ?int $groupCount = null,
        ?string $docsLink = null,
    ): string {
        $problem = "Backreference '\\{$referenceNumber}' references a non-existent group at {$position}";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/concepts/backreferences';
        }

        if ($groupCount !== null && (int) $referenceNumber > $groupCount) {
            $suggestion = "Only {$groupCount} group(s) exist, use backreference \\{$groupCount} or lower";
        } else {
            $suggestion = 'Check that the referenced capturing group exists before this backreference';
        }

        return self::buildSuggestion($problem, $suggestion, $docsLink);
    }

    /**
     * Build a helpful error message for lookbehind issues.
     *
     * @param string $issueType  Type of lookbehind issue
     * @param string $position    Where it was found
     * @param int|null $maxLength   Maximum lookbehind length (if applicable)
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildLookbehindSuggestion(
        string $issueType,
        string $position,
        ?int $maxLength = null,
        ?string $docsLink = null,
    ): string {
        $issues = [
            'variableLength' => [
                'problem' => 'Variable-length lookbehind is not supported at position ' . $position,
                'suggestion' => 'Use fixed-length pattern or replace with lookahead assertion if possible',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/concepts/lookarounds#lookbehind',
            ],
            'exceedsMaxLength' => [
                'problem' => "Lookbehind exceeds maximum length of {$maxLength} characters at {$position}",
                'suggestion' => 'Simplify lookbehind pattern or increase max_lookbehind_length configuration',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/configuration#lookbehind-length',
            ],
        ];

        if (!array_key_exists($issueType, $issues)) {
            return "Invalid lookbehind issue: {$issueType} at {$position}";
        }

        $info = $issues[$issueType];

        return self::buildSuggestion(
            $info['problem'],
            $info['suggestion'],
            $info['docs'],
        );
    }

    /**
     * Build a helpful error message for flag issues.
     *
     * @param string $flag        The invalid flag
     * @param string $position    Where it was found
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildFlagSuggestion(
        string $flag,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problem = "Invalid flag '{$flag}' at position {$position}";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/concepts/flags';
        }

        $validFlags = [
            'PCRE' => ['i', 'm', 's', 'x', 'u', 'A', 'D', 'S', 'U', 'X', 'J', 'r'],
            'Inline' => ['i', 'm', 's', 'x', 'u', 'A', 'D', 'S', 'U', 'X', 'J', 'r', '-', '^'],
        ];

        $suggestion = "Valid flags are: " . implode(', ', $validFlags['PCRE']) . "";

        return self::buildSuggestion($problem, $suggestion, $docsLink);
    }

    /**
     * Build a helpful error message for anchor issues.
     *
     * @param string $anchor      The anchor that caused the issue
     * @param string $position    Where it was found
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildAnchorSuggestion(
        string $anchor,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problems = [
            'conflict' => [
                'problem' => "Anchor '{$anchor}' conflicts with previous anchors at {$position}",
                'suggestion' => 'Use only one anchor type per position or use word boundaries (\\b)',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/concepts/anchors',
            ],
            'misplaced' => [
                'problem' => "Anchor '{$anchor}' is misplaced at {$position}",
                'suggestion' => 'Use anchors only at valid positions (start/end of pattern or inside groups)',
                'docs' => 'https://yoeunes.github.io/regex-parser/docs/concepts/anchors#positioning',
            ],
        ];

        if (!array_key_exists($anchor, $problems)) {
            return "Invalid anchor: {$anchor} at {$position}";
        }

        $info = $problems[$anchor];

        return self::buildSuggestion(
            $info['problem'],
            $info['suggestion'],
            $info['docs'],
        );
    }

    /**
     * Build a helpful error message for ReDoS issues.
     *
     * @param string $pattern     The pattern with ReDoS risk
     * @param string $position    Where risk was detected
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildRedoSuggestion(
        string $pattern,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problem = "Catastrophic backtracking (ReDoS) risk detected at {$position}";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/redos-guide';
        }

        $suggestion = "Consider using possessive quantifiers (++), atomic groups (?>), or anchors to limit backtracking";

        $codeExample = self::buildCodeExampleSuggestion(
            $problem,
            "/(a+)+\$/",
            "/a++\$/",
            "Possessive quantifier (++) prevents backtracking into the repeated group",
            $docsLink,
        );

        return $codeExample;
    }

    /**
     * Build a helpful error message for nested quantifiers.
     *
     * @param string $outerQuantifier  Outer quantifier (e.g., +, *)
     * @param string $innerQuantifier  Inner quantifier (e.g., +, *)
     * @param string $position         Where it was found
     * @param string|null $docsLink Optional link to documentation
     * @return string Formatted help message
     */
    private static function buildNestedQuantifierSuggestion(
        string $outerQuantifier,
        string $innerQuantifier,
        string $position,
        ?string $docsLink = null,
    ): string {
        $problem = "Nested quantifiers ({$outerQuantifier} contains {$innerQuantifier}) at {$position}";

        if ($docsLink === null) {
            $docsLink = 'https://yoeunes.github.io/regex-parser/docs/redos-guide#nested-quantifiers';
        }

        $suggestion = "Use possessive quantifier ({$outerQuantifier}{$outerQuantifier}) or atomic group to limit backtracking";

        $before = "/a{$innerQuantifier}+/";
        $after = "/a{$outerQuantifier}{$outerQuantifier}/";

        $codeExample = self::buildCodeExampleSuggestion(
            $problem,
            $before,
            $after,
            $suggestion,
            $docsLink,
        );

        return $codeExample;
    }

    /**
     * Get base documentation URL.
     *
     * @param string|null $specificPath Optional specific path for documentation
     * @return string Full documentation URL
     */
    private static function getDocsUrl(?string $specificPath = null): string
    {
        $baseUrl = 'https://yoeunes.github.io/regex-parser/docs/';

        if ($specificPath === null) {
            return $baseUrl;
        }

        $trimmedPath = ltrim($specificPath, '/');

        return $baseUrl . $trimmedPath;
    }
}
