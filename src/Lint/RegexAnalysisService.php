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

namespace RegexParser\Lint;

use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;
use RegexParser\ValidationResult;

use function count;

/**
 * Handles regex-related analysis and transformations.
 */
final readonly class RegexAnalysisService
{
    private const PATTERN_DELIMITERS = ['/', '#', '~', '%'];
    private const ISSUE_ID_COMPLEXITY = 'regex.lint.complexity';
    private const ISSUE_ID_REDOS = 'regex.lint.redos';
    private const RISK_LINT_ISSUE_IDS = [
        'regex.lint.quantifier.nested' => true,
        'regex.lint.dotstar.nested' => true,
    ];

    private ReDoSSeverity $redosSeverityThreshold;

    /**
     * @var list<string>
     */
    private array $ignoredPatterns;

    /**
     * @param list<string> $ignoredPatterns
     * @param list<string> $redosIgnoredPatterns
     */
    public function __construct(
        private Regex $regex,
        private ?RegexPatternExtractor $extractor = null,
        private int $warningThreshold = 50,
        string $redosThreshold = ReDoSSeverity::HIGH->value,
        array $ignoredPatterns = [],
        array $redosIgnoredPatterns = [],
        private bool $ignoreParseErrors = false,
    ) {
        $this->redosSeverityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;
        $this->ignoredPatterns = $this->buildIgnoredPatterns($ignoredPatterns, $redosIgnoredPatterns);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function scan(array $paths, array $excludePaths): array
    {
        $extractor = $this->extractor ?? new RegexPatternExtractor(
            new TokenBasedExtractionStrategy(),
        );

        return $extractor->extract($paths, $excludePaths);
    }

    /**
     * @param list<RegexPatternOccurrence> $patterns
     *
     * @return list<array{
     *     type: string,
     *     file: string,
     *     line: int,
     *     column: int,
     *     position?: int,
     *     message: string,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string,
     *     analysis?: ReDoSAnalysis,
     *     validation?: \RegexParser\ValidationResult
     * }>
     */
    public function lint(array $patterns, ?callable $progress = null): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source;
            if (!$validation->isValid) {
                $message = $validation->error ?? 'Invalid regex.';
                if ($this->ignoreParseErrors && $this->isLikelyPartialRegexError($message)) {
                    if ($progress) {
                        $progress();
                    }

                    continue;
                }

                $issues[] = [
                    'type' => 'error',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'column' => 1,
                    'position' => $validation->offset,
                    'message' => $message,
                    'source' => $source,
                    'validation' => $validation,
                    'tip' => $this->getTipForValidationError($message, $occurrence->pattern, $validation),
                ];

                if ($progress) {
                    $progress();
                }

                continue;
            }

            $ast = $this->regex->parse($occurrence->pattern);
            $linter = new LinterNodeVisitor();
            $ast->accept($linter);
            $skipRiskAnalysis = $this->shouldSkipRiskAnalysis($occurrence);

            foreach ($linter->getIssues() as $issue) {
                if ($skipRiskAnalysis && isset(self::RISK_LINT_ISSUE_IDS[$issue->id])) {
                    continue;
                }

                $issues[] = [
                    'type' => 'warning',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'column' => 1,
                    'position' => $issue->offset,
                    'issueId' => $issue->id,
                    'message' => $issue->message,
                    'hint' => $issue->hint,
                    'source' => $source,
                ];
            }

            if (!$skipRiskAnalysis) {
                if ($validation->complexityScore >= $this->warningThreshold) {
                    $issues[] = [
                        'type' => 'warning',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'column' => 1,
                        'issueId' => self::ISSUE_ID_COMPLEXITY,
                        'message' => \sprintf('Pattern is complex (score: %d).', $validation->complexityScore),
                        'source' => $source,
                    ];
                }

                $redos = $this->regex->redos($occurrence->pattern, $this->redosSeverityThreshold);
                if ($redos->exceedsThreshold($this->redosSeverityThreshold)) {
                    $issues[] = [
                        'type' => 'error',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'column' => 1,
                        'issueId' => self::ISSUE_ID_REDOS,
                        'message' => \sprintf(
                            'Pattern may be vulnerable to ReDoS (severity: %s).',
                            strtoupper($redos->severity->value),
                        ),
                        'hint' => $this->getReDoSHint($redos, $occurrence->pattern),
                        'source' => $source,
                        'analysis' => $redos,
                    ];
                }
            }

            if ($progress) {
                $progress();
            }
        }

        return $issues;
    }

    /**
     * @param list<RegexPatternOccurrence> $patterns
     *
     * @return list<array{file: string, line: int, analysis: ReDoSAnalysis}>
     */
    public function analyzeRedos(array $patterns, ReDoSSeverity $threshold): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            if (!$validation->isValid) {
                continue;
            }

            $analysis = $this->regex->redos($occurrence->pattern);
            if (!$analysis->exceedsThreshold($threshold)) {
                continue;
            }

            $issues[] = [
                'file' => $occurrence->file,
                'line' => $occurrence->line,
                'analysis' => $analysis,
            ];
        }

        return $issues;
    }

    /**
     * @param list<RegexPatternOccurrence>                           $patterns
     * @param array{digits?: bool, word?: bool, strictRanges?: bool} $optimizationConfig
     *
     * @return list<array{
     *     file: string,
     *     line: int,
     *     optimization: OptimizationResult,
     *     savings: int,
     *     source?: string
     * }>
     */
    public function suggestOptimizations(array $patterns, int $minSavings, array $optimizationConfig = []): array
    {
        $suggestions = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source;
            if (!$validation->isValid) {
                continue;
            }

            $isExtended = $this->usesExtendedMode($occurrence->pattern);

            try {
                if ($isExtended) {
                    // For verbose /x patterns, generate a normalized version
                    // that keeps the structure but collapses comments to
                    // lightweight placeholders like (?#...). This gives a
                    // compact view similar to:
                    //   (?#...)(?(DEFINE)(?<balanced>((?:(?#...)[^()]|(?#...)(?balanced))*(?#...))))...
                    // without destroying readability in the original source.
                    $ast = $this->regex->parse($occurrence->pattern);
                    $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor(false, true);
                    $normalized = $ast->accept($compiler);
                    $optimization = new OptimizationResult($occurrence->pattern, $normalized, ['Normalized extended pattern.']);
                } else {
                    $optimization = $this->regex->optimize($occurrence->pattern, $optimizationConfig);
                }
            } catch (\Throwable) {
                continue;
            }

            if (!$optimization->isChanged()) {
                continue;
            }

            $savings = \strlen($optimization->original) - \strlen($optimization->optimized);
            if ($savings < $minSavings) {
                continue;
            }

            $suggestions[] = [
                'file' => $occurrence->file,
                'line' => $occurrence->line,
                'optimization' => $optimization,
                'savings' => $savings,
                'source' => $source,
            ];
        }

        return $suggestions;
    }

    public function highlight(string $pattern): string
    {
        $ast = $this->regex->parse($pattern);

        return $ast->accept(new ConsoleHighlighterVisitor());
    }

    private function shouldSkipRiskAnalysis(RegexPatternOccurrence $occurrence): bool
    {
        $rawPattern = $occurrence->displayPattern ?? $occurrence->pattern;
        $fragment = $this->extractFragment($rawPattern);
        $body = $this->trimPatternBody($occurrence->pattern);

        return $this->isIgnored($fragment)
            || $this->isIgnored($body)
            || $this->isTriviallySafe($fragment)
            || $this->isTriviallySafe($body);
    }

    private function extractFragment(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last && \in_array($first, self::PATTERN_DELIMITERS, true)) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function trimPatternBody(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function isIgnored(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        return \in_array($body, $this->ignoredPatterns, true);
    }

    private function isTriviallySafe(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        $parts = explode('|', $body);
        if (\count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (!preg_match('#^[A-Za-z0-9._-]+$#', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $userIgnored
     * @param list<string> $redosIgnored
     *
     * @return list<string>
     */
    private function buildIgnoredPatterns(array $userIgnored, array $redosIgnored): array
    {
        return array_values(array_unique([...$redosIgnored, ...$userIgnored]));
    }

    /**
     * Detect whether a pattern uses extended (/x) mode, where whitespace and
     * inline comments are significant for readability. For such patterns we
     * avoid suggesting structural optimizations that would rewrite the pattern
     * into a single-line canonical form and drop comments.
     */
    private function usesExtendedMode(string $pattern): bool
    {
        // Fast path: if there is no trailing flag block, there's no /x.
        $pattern = ltrim($pattern);
        if ('' === $pattern) {
            return false;
        }

        try {
            /** @var array{0: string, 1: string, 2: string} $parts */
            $parts = \RegexParser\Internal\PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            // If we cannot reliably extract flags, fall back to not treating it
            // as extended mode to avoid false positives.
            return false;
        }

        $flags = $parts[1] ?? '';

        return \is_string($flags) && str_contains($flags, 'x');
    }

    private function getTipForValidationError(string $message, string $pattern, ValidationResult $validation): ?string
    {
        // Try to provide intelligent, pattern-specific tips
        $intelligentTip = $this->generateIntelligentTip($message, $pattern, $validation);
        if (null !== $intelligentTip) {
            return $intelligentTip;
        }

        // Fallback to generic tips
        return $this->getGenericTipForValidationError($message);
    }

    private function generateIntelligentTip(string $message, string $pattern, ValidationResult $validation): ?string
    {
        if (str_contains($message, 'No closing delimiter')) {
            return $this->suggestDelimiterFix($pattern);
        }

        if (str_contains($message, 'Unclosed character class')) {
            return $this->suggestCharacterClassFix($pattern, $validation);
        }

        if (str_contains($message, 'Invalid quantifier range')) {
            return $this->suggestQuantifierRangeFix($pattern, $validation);
        }

        if (str_contains($message, 'Backreference to non-existent group')) {
            return $this->suggestBackreferenceFix($pattern, $validation);
        }

        if (str_contains($message, 'Lookbehind is unbounded')) {
            return $this->suggestLookbehindFix($pattern, $validation);
        }

        return null;
    }

    private function suggestDelimiterFix(string $pattern): string
    {
        // Find the delimiter used
        if (!preg_match('/^([#~\-%@!])(.*)$/', $pattern, $matches)) {
            $matches = ['', '/', $pattern];
        }

        $delimiter = $matches[1];
        $content = $matches[2];

        // Check if delimiter appears in content
        if (str_contains($content, $delimiter)) {
            $escaped = preg_quote($delimiter, '/');

            return "Your pattern contains the delimiter '$delimiter' inside. Either escape it as \\$delimiter or use a different delimiter like #pattern#.";
        }

        // Missing closing delimiter
        $suggested = $pattern.$delimiter;

        return "Add the missing closing delimiter: $suggested";
    }

    private function suggestCharacterClassFix(string $pattern, ValidationResult $validation): ?string
    {
        // For patterns like /[a-z/ we need to add ] before the final delimiter
        if (str_contains($pattern, '[') && !str_contains($pattern, ']')) {
            // Find the last delimiter
            $lastDelimiterPos = strrpos($pattern, '/');
            if (false !== $lastDelimiterPos) {
                $suggested = substr_replace($pattern, ']', $lastDelimiterPos, 0);

                return "Add missing closing bracket: $suggested";
            }
        }

        return null;
    }

    private function suggestQuantifierRangeFix(string $pattern, ValidationResult $validation): ?string
    {
        // Look for quantifier ranges in the pattern
        if (preg_match('/\{(\d+),(\d+)\}/', $pattern, $matches, \PREG_OFFSET_CAPTURE)) {
            $min = (int) $matches[1][0];
            $max = (int) $matches[2][0];
            $offset = $matches[0][1];

            if ($min > $max) {
                $fixed = '{'.$max.','.$min.'}';
                $suggested = str_replace($matches[0][0], $fixed, $pattern);

                return "Swap min and max values: $suggested";
            }
        }

        return null;
    }

    private function suggestBackreferenceFix(string $pattern, ValidationResult $validation): ?string
    {
        // Find all backreferences in the pattern
        if (preg_match_all('/\\\\(\d+)/', $pattern, $matches, \PREG_OFFSET_CAPTURE)) {
            // Count opening parentheses (capturing groups)
            $openCount = substr_count($pattern, '(');

            foreach ($matches[1] as $match) {
                $refNum = (int) $match[0];
                if ($refNum > $openCount) {
                    return "Backreference \\$refNum refers to group $refNum, but only $openCount capturing groups exist in the pattern. Valid backreferences are \\1 through \\$openCount.";
                }
            }
        }

        return null;
    }

    private function suggestLookbehindFix(string $pattern, ValidationResult $validation): ?string
    {
        $offset = $validation->offset ?? 0;

        // Find the lookbehind content
        $before = substr($pattern, 0, $offset);
        $lookbehindStart = strrpos($before, '(?<=');

        if (false === $lookbehindStart) {
            $lookbehindStart = strrpos($before, '(?<!');
        }

        if (false === $lookbehindStart) {
            return null;
        }

        $lookbehindContent = substr($pattern, $lookbehindStart, $offset - $lookbehindStart);

        // Check for unbounded quantifiers in lookbehind
        if (preg_match('/[+*][?]?/', $lookbehindContent)) {
            return "Replace unbounded quantifiers in lookbehind with fixed-length alternatives. For example, change (?<=\w*) to (?<=\w{0,10}) with an appropriate maximum length.";
        }

        return null;
    }

    private function getGenericTipForValidationError(string $message): ?string
    {
        if (str_contains($message, 'No closing delimiter')) {
            return 'Escape "/" inside the pattern (\/) or use a different delimiter, e.g. #pattern#.';
        }

        if (str_contains($message, 'Unclosed character class')) {
            return 'Character classes must be closed with "]". Check for missing or extra "[".';
        }

        if (str_contains($message, 'Invalid quantifier range')) {
            return 'Quantifier ranges must have min <= max. For example, {3,2} is invalid; use {2,3} or {2} instead.';
        }

        if (str_contains($message, 'Unknown regex flag')) {
            return 'Only valid PCRE flags are: i (case-insensitive), m (multiline), s (dot matches newline), x (extended), U (ungreedy), J (duplicate names).';
        }

        if (str_contains($message, 'Backreference to non-existent group')) {
            return 'Backreferences like \\1 refer to capturing groups. Make sure the group number exists.';
        }

        if (str_contains($message, 'Lookbehind is unbounded')) {
            return 'Variable-length lookbehinds are not allowed in PCRE. Use fixed-length alternatives like (?<=\w{3}) instead of (?<=\w*).';
        }

        if (str_contains($message, 'Invalid conditional construct')) {
            return 'Conditionals need a valid condition: group reference (?(1)...), lookaround (?(?=...)...), or (?(DEFINE)...).';
        }

        return null;
    }

    private function getReDoSHint(ReDoSAnalysis $analysis, string $pattern): string
    {
        $hints = [];

        if (!empty($analysis->recommendations)) {
            $hints = array_merge($hints, $analysis->recommendations);
        }

        if (null !== $analysis->vulnerableSubpattern) {
            $hints[] = \sprintf('The vulnerable part is: %s', $analysis->vulnerableSubpattern);
        }

        // Try to suggest specific fixes based on the pattern
        $patternHints = $this->suggestReDoSFixes($pattern, $analysis);
        if (!empty($patternHints)) {
            $hints = array_merge($hints, $patternHints);
        }

        if (empty($hints)) {
            $hints[] = 'ReDoS occurs when regex engines spend excessive time backtracking. Use possessive quantifiers (+ instead of *) or atomic groups (?>...) to prevent it.';
        }

        $hints[] = 'Test with malicious inputs like repeated strings followed by a non-matching character.';

        return implode(' ', $hints);
    }

    /**
     * @return list<string>
     */
    private function suggestReDoSFixes(string $pattern, ReDoSAnalysis $analysis): array
    {
        $hints = [];

        // Look for common vulnerable patterns and suggest fixes
        if (preg_match('/(\([^)]*\)\+)|\(\([^)]*\+\)\)/', $pattern)) {
            $hints[] = 'Replace nested quantifiers like (a+)+ with atomic groups: (?>a+) or possessive quantifiers: a++';
        }

        if (str_contains($pattern, '.*') && str_contains($pattern, '+')) {
            $hints[] = 'Consider using possessive quantifiers .*+ instead of .* to prevent backtracking.';
        }

        if (preg_match('/\([^)]*\*\)/', $pattern)) {
            $hints[] = 'Replace * with *+ (possessive) in groups to prevent backtracking: (?>...)';
        }

        // If we have a vulnerable subpattern, try to suggest a specific fix
        if (null !== $analysis->vulnerableSubpattern) {
            $vulnerable = $analysis->vulnerableSubpattern;
            if (preg_match('/(\w+)\+(\)\+)/', $vulnerable, $matches)) {
                $char = $matches[1];
                $hints[] = "Replace ($char+)+ with atomic group: (?>$char+) or possessive: $char++";
            }
        }

        return $hints;
    }

    private function isLikelyPartialRegexError(string $errorMessage): bool
    {
        $indicators = [
            'No closing delimiter',
            'Regex too short',
            'Unknown modifier',
            'Unexpected end',
        ];

        foreach ($indicators as $indicator) {
            if (false !== stripos($errorMessage, (string) $indicator)) {
                return true;
            }
        }

        return false;
    }
}
