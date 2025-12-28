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

namespace RegexParser\Bridge\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

/**
 * Validates regex patterns in `preg_*` functions for syntax and ReDoS vulnerabilities.
 *
 * @implements Rule<FuncCall>
 */
final class PregValidationRule implements Rule
{
    public const IDENTIFIER_SYNTAX_INVALID = 'regex.syntax.invalid';
    public const IDENTIFIER_SYNTAX_DELIMITER = 'regex.syntax.delimiter';
    public const IDENTIFIER_SYNTAX_EMPTY = 'regex.syntax.empty';
    public const IDENTIFIER_REDOS_CRITICAL = 'regex.redos.critical';
    public const IDENTIFIER_REDOS_HIGH = 'regex.redos.high';
    public const IDENTIFIER_REDOS_MEDIUM = 'regex.redos.medium';
    public const IDENTIFIER_REDOS_LOW = 'regex.redos.low';
    public const IDENTIFIER_OPTIMIZATION = 'regex.optimization';

    private const ISSUE_ID_REDOS = 'regex.lint.redos';
    private const ISSUE_ID_COMPLEXITY = 'regex.lint.complexity';

    private const PREG_FUNCTION_MAP = [
        'preg_match' => 0,
        'preg_match_all' => 0,
        'preg_replace' => 0,
        'preg_replace_callback' => 0,
        'preg_split' => 0,
        'preg_grep' => 0,
        'preg_filter' => 0,
        'preg_replace_callback_array' => 0,
    ];

    private const DOC_BASE_URL = 'https://github.com/yoeunes/regex-parser/blob/main/docs/reference.md';

    private const DOC_LINKS = [
        // Flags - Use PHP.net where possible, or precise concept pages
        'Flag \'s\' is useless' => self::DOC_BASE_URL.'#useless-flag-s-dotall',
        'Flag \'m\' is useless' => self::DOC_BASE_URL.'#useless-flag-m-multiline',
        'Flag \'i\' is useless' => self::DOC_BASE_URL.'#useless-flag-i-caseless',

        // Security & Concepts - The authority on explaining regex mechanics
        'catastrophic backtracking' => self::DOC_BASE_URL.'#catastrophic-backtracking',

        // Advanced Syntax - Internal documentation
        'possessive quantifiers' => self::DOC_BASE_URL.'#possessive-quantifiers',
        'atomic groups' => self::DOC_BASE_URL.'#atomic-groups',

        // Assertions
        'lookahead' => self::DOC_BASE_URL.'#assertions',
        'lookbehind' => self::DOC_BASE_URL.'#assertions',
    ];

    private const LINT_DOC_LINKS = [
        'regex.lint.flag.useless.s' => self::DOC_BASE_URL.'#useless-flag-s-dotall',
        'regex.lint.flag.useless.m' => self::DOC_BASE_URL.'#useless-flag-m-multiline',
        'regex.lint.flag.useless.i' => self::DOC_BASE_URL.'#useless-flag-i-caseless',
        'regex.lint.anchor.impossible.start' => self::DOC_BASE_URL.'#anchor-conflicts',
        'regex.lint.anchor.impossible.end' => self::DOC_BASE_URL.'#anchor-conflicts',
        'regex.lint.quantifier.nested' => self::DOC_BASE_URL.'#nested-quantifiers',
        'regex.lint.dotstar.nested' => self::DOC_BASE_URL.'#dot-star-in-quantifier',
        'regex.lint.group.redundant' => self::DOC_BASE_URL.'#redundant-non-capturing-group',
        'regex.lint.alternation.duplicate' => self::DOC_BASE_URL.'#duplicate-alternation-branches',
        'regex.lint.alternation.overlap' => self::DOC_BASE_URL.'#overlapping-alternation-branches',
        'regex.lint.overlap.charset' => self::DOC_BASE_URL.'#overlapping-alternation-branches',
        'regex.lint.charclass.redundant' => self::DOC_BASE_URL.'#redundant-character-class-elements',
        'regex.lint.escape.suspicious' => self::DOC_BASE_URL.'#suspicious-escapes',
        'regex.lint.flag.redundant' => self::DOC_BASE_URL.'#inline-flag-redundant',
        'regex.lint.flag.override' => self::DOC_BASE_URL.'#inline-flag-override',
    ];

    private ?RegexAnalysisService $analysis = null;

    /**
     * @param bool                                                $ignoreParseErrors  Ignore parse errors for partial regex strings
     * @param bool                                                $reportRedos        Report ReDoS vulnerability analysis
     * @param string                                              $redosThreshold     Minimum ReDoS severity level to report
     * @param array{digits: bool, word: bool, strictRanges: bool} $optimizationConfig
     */
    public function __construct(
        private readonly bool $ignoreParseErrors = true,
        private readonly bool $reportRedos = true,
        private readonly string $redosThreshold = 'high',
        private readonly bool $suggestOptimizations = false,
        private readonly array $optimizationConfig = ['digits' => true, 'word' => true, 'strictRanges' => true],
    ) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return array<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toLowerString();
        if (!isset(self::PREG_FUNCTION_MAP[$functionName])) {
            return [];
        }

        $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
        $args = $node->getArgs();

        if (!isset($args[$patternArgPosition])) {
            return [];
        }

        $patternArg = $args[$patternArgPosition]->value;

        if ('preg_replace_callback_array' === $functionName) {
            return $this->processPregReplaceCallbackArray($patternArg, $scope, $node->getLine(), $functionName);
        }

        $errors = [];
        foreach ($scope->getType($patternArg)->getConstantStrings() as $constantString) {
            $errors = array_merge(
                $errors,
                $this->validatePattern($constantString->getValue(), $node->getLine(), $scope, $functionName),
            );
        }

        return $errors;
    }

    public function isOptimizationFormatSafe(string $original, string $optimized): bool
    {
        $delimiter = $optimized[0] ?? '';
        if ('' === $delimiter) {
            return false;
        }

        $lastDelimiterPos = strrpos($optimized, $delimiter);
        if (false === $lastDelimiterPos || 0 === $lastDelimiterPos) {
            return false;
        }

        $patternPart = substr($optimized, 1, $lastDelimiterPos - 1);

        $originalDelimiter = $original[0] ?? '';
        $originalPatternPart = '';
        if ('' !== $originalDelimiter) {
            $originalLastPos = strrpos($original, $originalDelimiter);
            if (false !== $originalLastPos) {
                $originalPatternPart = substr($original, 1, $originalLastPos - 1);
            }
        }

        if ('' === $patternPart) {
            return false;
        }

        if (\strlen($patternPart) < 2) {
            return false;
        }

        if (str_contains($originalPatternPart, '\n') && !str_contains($patternPart, '\n')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<IdentifierRuleError>
     */
    private function processPregReplaceCallbackArray(Node $arrayNode, Scope $scope, int $lineNumber, string $functionName): array
    {
        if (!$arrayNode instanceof Array_) {
            return [];
        }

        $errors = [];
        foreach ($arrayNode->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_) {
                continue;
            }

            $pattern = $item->key->value;
            $errors = array_merge(
                $errors,
                $this->validatePattern($pattern, $lineNumber, $scope, $functionName),
            );
        }

        return $errors;
    }

    /**
     * @return array<IdentifierRuleError>
     */
    private function validatePattern(string $pattern, int $lineNumber, Scope $scope, string $functionName): array
    {
        if ('' === $pattern) {
            return [
                RuleErrorBuilder::message('Regex pattern cannot be empty.')
                    ->line($lineNumber)
                    ->identifier(self::IDENTIFIER_SYNTAX_EMPTY)
                    ->build(),
            ];
        }

        $errors = [];
        $occurrence = new RegexPatternOccurrence(
            $pattern,
            $scope->getFile(),
            $lineNumber,
            $this->formatSource($functionName),
        );
        $issues = $this->getAnalysisService()->lint([$occurrence]);

        foreach ($issues as $issue) {
            if (null === ($issue['issueId'] ?? null)) {
                $shortPattern = $this->truncatePattern($pattern);
                $message = $this->firstLine((string) ($issue['message'] ?? 'Invalid regex.'));
                $errors[] = RuleErrorBuilder::message(\sprintf('Regex syntax error: %s (Pattern: "%s")', $message, $shortPattern))
                    ->line($lineNumber)
                    ->identifier($this->getIdentifierForSyntaxError($message))
                    ->build();

                return $errors;
            }
        }

        $redosIssues = [];
        $lintIssues = [];
        foreach ($issues as $issue) {
            $issueId = $issue['issueId'] ?? null;
            if (self::ISSUE_ID_COMPLEXITY === $issueId) {
                continue;
            }

            if (self::ISSUE_ID_REDOS === $issueId) {
                $redosIssues[] = $issue;

                continue;
            }

            $lintIssues[] = $issue;
        }

        foreach ($redosIssues as $issue) {
            if (!$this->reportRedos) {
                continue;
            }

            $analysis = $issue['analysis'] ?? null;
            // @codeCoverageIgnoreStart
            if (!$analysis instanceof ReDoSAnalysis) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $identifier = match ($analysis->severity) {
                ReDoSSeverity::CRITICAL => self::IDENTIFIER_REDOS_CRITICAL,
                ReDoSSeverity::HIGH => self::IDENTIFIER_REDOS_HIGH,
                ReDoSSeverity::MEDIUM => self::IDENTIFIER_REDOS_MEDIUM,
                default => self::IDENTIFIER_REDOS_LOW,
            };

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'ReDoS vulnerability detected (%s): %s',
                strtoupper($analysis->severity->value),
                $this->truncatePattern($pattern),
            ))
                ->line($lineNumber)
                ->tip($this->getTipForReDoS($analysis->recommendations))
                ->identifier($identifier)
                ->build();
        }

        if ($this->suggestOptimizations) {
            /** @var array<array{file: string, line: int, optimization: OptimizationResult, savings: int, source?: string}> $optimizations */
            $optimizations = $this->getAnalysisService()->suggestOptimizations(
                [$occurrence],
                1,
                $this->optimizationConfig,
            );

            foreach ($optimizations as $optimizationEntry) {
                /** @var OptimizationResult $optimization */
                $optimization = $optimizationEntry['optimization'];
                if (!$this->isOptimizationFormatSafe($pattern, $optimization->optimized)) {
                    continue;
                }
                $shortPattern = $this->truncatePattern($pattern);
                $errors[] = RuleErrorBuilder::message(\sprintf('Regex pattern can be optimized: "%s"', $shortPattern))
                    ->line($lineNumber)
                    ->identifier(self::IDENTIFIER_OPTIMIZATION)
                    ->tip(\sprintf('Consider using: %s', $optimization->optimized))
                    ->build();
            }
        }

        foreach ($lintIssues as $issue) {
            $issueId = $issue['issueId'] ?? null;
            // @codeCoverageIgnoreStart
            if (!\is_string($issueId) || '' === $issueId) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $builder = RuleErrorBuilder::message((string) $issue['message'])
                ->line($lineNumber)
                ->identifier($issueId);
            $tipParts = [];
            $hint = $issue['hint'] ?? null;
            if (null !== $hint && '' !== $hint) {
                $tipParts[] = $hint;
            }
            if (isset(self::LINT_DOC_LINKS[$issueId])) {
                $tipParts[] = 'Read more: '.self::LINT_DOC_LINKS[$issueId];
            }
            if ([] !== $tipParts) {
                $builder = $builder->tip(implode("\n", $tipParts));
            }
            $errors[] = $builder->build();
        }

        return $errors;
    }

    private function getIdentifierForSyntaxError(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'delimiter')) {
            return self::IDENTIFIER_SYNTAX_DELIMITER;
        }

        return self::IDENTIFIER_SYNTAX_INVALID;
    }

    private function truncatePattern(string $pattern, int $length = 50): string
    {
        return \strlen($pattern) > $length ? substr($pattern, 0, $length).'...' : $pattern;
    }

    private function firstLine(string $message): string
    {
        $lines = explode("\n", $message);

        return $lines[0] ?? $message;
    }

    private function formatSource(string $functionName): string
    {
        return 'php:'.$functionName.'()';
    }

    private function getAnalysisService(): RegexAnalysisService
    {
        return $this->analysis ??= new RegexAnalysisService(
            Regex::create(),
            null,
            redosThreshold: $this->redosThreshold,
            ignoreParseErrors: $this->ignoreParseErrors,
        );
    }

    /**
     * @param array<string> $recommendations
     */
    private function getTipForReDoS(array $recommendations): string
    {
        $tip = implode("\n", $recommendations);

        // Append links for relevant recommendations
        $additionalLinks = [];
        if (str_contains($tip, 'possessive quantifiers') || str_contains($tip, 'possessive')) {
            $additionalLinks[] = 'Read more about possessive quantifiers: '.self::DOC_LINKS['possessive quantifiers'];
        }
        if (str_contains($tip, 'atomic groups')) {
            $additionalLinks[] = 'Read more about atomic groups: '.self::DOC_LINKS['atomic groups'];
        }

        // Always append catastrophic backtracking link for ReDoS
        $additionalLinks[] = 'Read more about catastrophic backtracking: '.self::DOC_LINKS['catastrophic backtracking'];

        $tip .= "\n\n".implode("\n", $additionalLinks);

        return $tip;
    }
}
