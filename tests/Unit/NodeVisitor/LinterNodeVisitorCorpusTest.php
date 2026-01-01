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
use RegexParser\Internal\PatternParser;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

final class LinterNodeVisitorCorpusTest extends TestCase
{
    private const ARROW = "\xE2\x86\x92";

    #[DataProvider('provideCorpusCases')]
    public function test_corpus_warnings_are_reported(string $pattern, array $expectedIssueIds): void
    {
        $regex = Regex::create()->parse($pattern);
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
            }
        }

        if (\in_array('regex.lint.flag.useless.m', $issueIds, true) && self::patternHasAnchors($pattern)) {
            $issueIds = array_values(array_diff($issueIds, ['regex.lint.flag.useless.m']));
        }

        return $issueIds;
    }

    private static function patternHasAnchors(string $pattern): bool
    {
        try {
            [$body] = PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            return false;
        }

        $body = preg_replace('/\\[(?:\\\\.|[^\\]]++)*\\]/', '', $body) ?? $body;

        return preg_match('/(?<!\\\\)[\\^$]/', $body) > 0;
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

        throw new \RuntimeException(\sprintf('Unmapped warning: %s', $message));
    }
}
