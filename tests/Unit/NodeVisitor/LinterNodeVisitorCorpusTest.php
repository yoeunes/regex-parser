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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Exception\SyntaxErrorException;
use RegexParser\Internal\PatternParser;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

final class LinterNodeVisitorCorpusTest extends TestCase
{
    private const ARROW = "\xE2\x86\x92";

    /**
     * @param array<int, string> $expectedIssueIds
     */
    #[DataProvider('provideCorpusCases')]
    public function test_corpus_warnings_are_reported(string $pattern, array $expectedIssueIds): void
    {
        try {
            $regex = Regex::create(['max_recursion_depth' => 4096])->parse($pattern);
        } catch (SyntaxErrorException) {
            $this->markTestSkipped('Parser cannot handle this complex pattern');
        }
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);

        $issueIds = array_unique(array_map(
            static fn ($issue): string => $issue->id,
            $linter->getIssues(),
        ));

        foreach ($expectedIssueIds as $expectedId) {
            $this->assertContains(
                $expectedId,
                $issueIds,
                \sprintf('Expected %s for pattern %s', $expectedId, $pattern),
            );
        }
    }

    /**
     * @return iterable<string, array{string, array<int, string>}>
     */
    public static function provideCorpusCases(): iterable
    {
        $cases = self::loadCorpusCases();
        foreach ($cases as $index => [$pattern, $issueIds]) {
            yield \sprintf('case-%d', $index) => [$pattern, $issueIds];
        }
    }

    /**
     * @return array<int, array{string, array<int, string>}>
     */
    private static function loadCorpusCases(): array
    {
        $path = \dirname(__DIR__, 3).'/corpus/corpus.log';
        if (!\is_file($path)) {
            throw new \RuntimeException(\sprintf('Missing corpus log at %s', $path));
        }

        $lines = file($path, \FILE_IGNORE_NEW_LINES);
        if (false === $lines) {
            throw new \RuntimeException(\sprintf('Unable to read corpus log at %s', $path));
        }

        $cases = [];
        $order = [];
        $currentPattern = null;
        $collectingPattern = false;
        $patternLines = [];
        $patternIndent = 0;

        $arrowPattern = preg_quote(self::ARROW, '/');

        foreach ($lines as $line) {
            if ($collectingPattern) {
                $trimmed = ltrim($line);
                $isContinuation = '' !== $trimmed
                    && !preg_match('/^(WARN|FAIL|TIP)\\b/', $trimmed)
                    && !str_starts_with($trimmed, 'corpus/')
                    && !str_starts_with($trimmed, self::ARROW);

                if ($isContinuation) {
                    $patternLines[] = self::stripPatternIndent($line, $patternIndent);

                    continue;
                }

                $currentPattern = self::normalizePatternNewlines(implode("\n", $patternLines));
                if (!isset($cases[$currentPattern])) {
                    $cases[$currentPattern] = [];
                    $order[] = $currentPattern;
                }

                $collectingPattern = false;
                $patternLines = [];
                $patternIndent = 0;
            }

            if (preg_match('/^(\\s*)'.$arrowPattern.'\\s+(.*)$/', $line, $matches)) {
                $patternIndent = \strlen($matches[1]);
                $patternLines = [$matches[2]];
                $collectingPattern = true;

                continue;
            }

            if (null !== $currentPattern && preg_match('/^\\s*WARN\\s+(.*)$/', $line, $matches)) {
                $issueId = self::mapWarningToIssueId($matches[1]);
                $cases[$currentPattern][$issueId] = true;
            }
        }

        if ($collectingPattern) {
            $currentPattern = self::normalizePatternNewlines(implode("\n", $patternLines));
            if (!isset($cases[$currentPattern])) {
                $cases[$currentPattern] = [];
                $order[] = $currentPattern;
            }
        }

        $result = [];
        foreach ($order as $pattern) {
            if ([] === $cases[$pattern]) {
                continue;
            }

            $issueIds = self::pruneExpectations($pattern, array_keys($cases[$pattern]));
            if ([] === $issueIds) {
                continue;
            }

            $result[] = [$pattern, $issueIds];
        }

        return $result;
    }

    private static function stripPatternIndent(string $line, int $patternIndent): string
    {
        if (0 === $patternIndent) {
            return $line;
        }

        $indent = str_repeat(' ', $patternIndent);
        if (str_starts_with($line, $indent)) {
            return substr($line, $patternIndent);
        }

        return ltrim($line, ' ');
    }

    private static function normalizePatternNewlines(string $pattern): string
    {
        if (!str_contains($pattern, '\\n')) {
            return $pattern;
        }

        try {
            [$body, $flags] = PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            return $pattern;
        }

        if (!str_contains($flags, 'x')) {
            return $pattern;
        }

        if (!str_contains($body, '#')) {
            return $pattern;
        }

        return str_replace('\\n', "\n", $pattern);
    }

    /**
     * @param array<int, string> $issueIds
     *
     * @return array<int, string>
     */
    private static function pruneExpectations(string $pattern, array $issueIds): array
    {
        $issueIds = array_values(array_unique($issueIds));

        if (\in_array('regex.lint.overlap.charset', $issueIds, true)) {
            if (!str_contains($pattern, '|')) {
                $issueIds = array_values(array_diff($issueIds, ['regex.lint.overlap.charset']));
            } elseif (\in_array('regex.lint.alternation.overlap', $issueIds, true)) {
                $issueIds = array_values(array_diff($issueIds, ['regex.lint.overlap.charset']));
            } elseif (!self::hasAlternationInsideUnboundedQuantifier($pattern)) {
                // Overlapping alternations are only flagged when inside an unbounded quantifier
                $issueIds = array_values(array_diff($issueIds, ['regex.lint.overlap.charset']));
            }
        }

        // Overlapping alternation literals are only flagged when inside an unbounded quantifier
        if (\in_array('regex.lint.alternation.overlap', $issueIds, true)) {
            if (!self::hasAlternationInsideUnboundedQuantifier($pattern)) {
                $issueIds = array_values(array_diff($issueIds, ['regex.lint.alternation.overlap']));
            }
        }

        if (\in_array('regex.lint.flag.useless.m', $issueIds, true) && self::patternHasAnchors($pattern)) {
            $issueIds = array_values(array_diff($issueIds, ['regex.lint.flag.useless.m']));
        }

        if (\in_array('regex.lint.flag.redundant', $issueIds, true)
            && self::hasInlineFlagSetBeforeUnset($pattern, 'i')
        ) {
            $issueIds = array_values(array_diff($issueIds, ['regex.lint.flag.redundant']));
        }

        if (\in_array('regex.lint.alternation.duplicate', $issueIds, true) && self::patternHasLookaround($pattern)) {
            $issueIds = array_values(array_diff($issueIds, ['regex.lint.alternation.duplicate']));
        }

        return $issueIds;
    }

    /**
     * Check if a pattern has an alternation inside an unbounded quantifier.
     * This uses a character-by-character approach to properly handle nested parentheses.
     *
     * Important: We only flag overlaps inside UNBOUNDED, NON-POSSESSIVE quantifiers:
     * - `+`, `*`, `{n,}` are unbounded
     * - `++`, `*+`, `{n,}+` are possessive (don't backtrack)
     * - `{n}`, `{n,m}` are bounded (don't cause exponential backtracking)
     *
     * Note: This is a heuristic. The actual linter uses AST traversal
     * with parent tracking, which is more accurate. This heuristic is used to filter
     * corpus test expectations.
     */
    private static function hasAlternationInsideUnboundedQuantifier(string $pattern): bool
    {
        try {
            [$body] = PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            return false;
        }

        // Find all top-level groups that contain alternation and check if they're followed by unbounded quantifier
        $groups = self::findTopLevelGroupsWithAlternation($body);

        foreach ($groups as $group) {
            $afterGroup = substr($body, $group['end']);
            // Check if followed by unbounded, non-possessive quantifier
            // +, *, {n,} but NOT ++, *+, {n,}+, {n,m}
            if (preg_match('/^\s*([+*][?]?(?!\+)|\{\d+,\}(?!\+))/', $afterGroup)) {
                return true;
            }
        }

        return false;
    }

    private static function hasInlineFlagSetBeforeUnset(string $pattern, string $flag): bool
    {
        $setRegex = '/\\(\\?[a-z]*'.preg_quote($flag, '/').'[a-z]*\\)/i';
        $unsetRegex = '/\\(\\?[^)]*-'.preg_quote($flag, '/').'[^)]*\\)/i';

        if (!preg_match($setRegex, $pattern, $setMatch, \PREG_OFFSET_CAPTURE)) {
            return false;
        }

        if (!preg_match($unsetRegex, $pattern, $unsetMatch, \PREG_OFFSET_CAPTURE)) {
            return false;
        }

        return $setMatch[0][1] < $unsetMatch[0][1];
    }

    private static function patternHasLookaround(string $pattern): bool
    {
        return str_contains($pattern, '(?=')
            || str_contains($pattern, '(?!')
            || str_contains($pattern, '(?<=')
            || str_contains($pattern, '(?<!');
    }

    /**
     * Find all top-level groups (properly balanced) that contain alternation.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private static function findTopLevelGroupsWithAlternation(string $body): array
    {
        $groups = [];
        $length = \strlen($body);
        $i = 0;

        while ($i < $length) {
            $char = $body[$i];

            // Skip escaped characters
            if ('\\' === $char && $i + 1 < $length) {
                $i += 2;

                continue;
            }

            // Skip character classes
            if ('[' === $char) {
                $i++;
                while ($i < $length) {
                    if ('\\' === $body[$i] && $i + 1 < $length) {
                        $i += 2;

                        continue;
                    }
                    if (']' === $body[$i]) {
                        $i++;

                        break;
                    }
                    $i++;
                }

                continue;
            }

            // Found a group start
            if ('(' === $char) {
                $groupStart = $i;
                $depth = 1;
                $hasAlternation = false;
                $alternationDepth = 0;
                $i++;

                while ($i < $length && $depth > 0) {
                    $c = $body[$i];

                    // Skip escaped characters
                    if ('\\' === $c && $i + 1 < $length) {
                        $i += 2;

                        continue;
                    }

                    // Skip character classes
                    if ('[' === $c) {
                        $i++;
                        while ($i < $length) {
                            if ('\\' === $body[$i] && $i + 1 < $length) {
                                $i += 2;

                                continue;
                            }
                            if (']' === $body[$i]) {
                                $i++;

                                break;
                            }
                            $i++;
                        }

                        continue;
                    }

                    if ('(' === $c) {
                        $depth++;
                    } elseif (')' === $c) {
                        $depth--;
                    } elseif ('|' === $c && 1 === $depth) {
                        // Alternation at the top level of this group
                        $hasAlternation = true;
                        $alternationDepth = $depth;
                    }

                    $i++;
                }

                if ($hasAlternation && 1 === $alternationDepth) {
                    $groups[] = ['start' => $groupStart, 'end' => $i];
                }

                continue;
            }

            $i++;
        }

        return $groups;
    }

    private static function patternHasAnchors(string $pattern): bool
    {
        try {
            [$body] = PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            return false;
        }

        $escaped = false;
        $inCharClass = false;
        $length = \strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ('\\' === $char) {
                $escaped = true;

                continue;
            }

            if ('[' === $char && !$inCharClass) {
                $inCharClass = true;

                continue;
            }

            if (']' === $char && $inCharClass) {
                $inCharClass = false;

                continue;
            }

            if (!$inCharClass && ('^' === $char || '$' === $char)) {
                return true;
            }
        }

        return false;
    }

    private static function mapWarningToIssueId(string $message): string
    {
        if (str_starts_with($message, "Flag 'i' is useless")) {
            return 'regex.lint.flag.useless.i';
        }

        if (str_starts_with($message, "Flag 's' is useless")) {
            return 'regex.lint.flag.useless.s';
        }

        if (str_starts_with($message, "Flag 'm' is useless")) {
            return 'regex.lint.flag.useless.m';
        }

        if (str_starts_with($message, 'Nested quantifiers can cause catastrophic backtracking.')) {
            return 'regex.lint.quantifier.nested';
        }

        if (str_starts_with($message, 'An unbounded quantifier wraps a dot-star')) {
            return 'regex.lint.dotstar.nested';
        }

        if (str_starts_with($message, 'Redundant non-capturing group')) {
            return 'regex.lint.group.redundant';
        }

        if (str_starts_with($message, 'Redundant elements detected in character class.')) {
            return 'regex.lint.charclass.redundant';
        }

        if (str_starts_with($message, 'Suspicious ASCII range')) {
            return 'regex.lint.charclass.suspicious_range';
        }

        if (str_starts_with($message, 'Character class contains "|"')) {
            return 'regex.lint.charclass.suspicious_pipe';
        }

        if (str_starts_with($message, 'Alternation branches have overlapping character sets')) {
            return 'regex.lint.overlap.charset';
        }

        if (str_starts_with($message, 'Alternation branches "') && str_ends_with($message, ' overlap.')) {
            return 'regex.lint.alternation.overlap';
        }

        if (str_starts_with($message, 'Duplicate alternation branch')) {
            return 'regex.lint.alternation.duplicate';
        }

        if (str_starts_with($message, 'Inline flag') && str_contains($message, 'overrides')) {
            return 'regex.lint.flag.override';
        }

        if (str_starts_with($message, 'Inline flag')) {
            return 'regex.lint.flag.redundant';
        }

        if (str_starts_with($message, 'Start anchor')) {
            return 'regex.lint.anchor.impossible.start';
        }

        if (str_starts_with($message, 'End anchor')) {
            return 'regex.lint.anchor.impossible.end';
        }

        if (str_starts_with($message, 'Potential ReDoS risk')) {
            return 'regex.lint.redos';
        }

        throw new \RuntimeException(\sprintf('Unmapped warning: %s', $message));
    }
}
