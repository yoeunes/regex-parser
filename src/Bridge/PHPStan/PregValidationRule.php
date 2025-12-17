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
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Exception\SyntaxErrorException;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\ReDoS\ReDoSAnalyzer;
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

    private const DOC_BASE_URL = 'https://github.com/yoeunes/regex-parser/blob/master/docs/reference.md';

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

    private ?Regex $regex = null;

    private ?ValidatorNodeVisitor $validator = null;

    private ?ReDoSAnalyzer $redosAnalyzer = null;

    /**
     * @param bool                            $ignoreParseErrors  Ignore parse errors for partial regex strings
     * @param bool                            $reportRedos        Report ReDoS vulnerability analysis
     * @param string                          $redosThreshold     Minimum ReDoS severity level to report
     * @param array{digits: bool, word: bool} $optimizationConfig
     */
    public function __construct(
        private readonly bool $ignoreParseErrors = true,
        private readonly bool $reportRedos = true,
        private readonly string $redosThreshold = 'high',
        private readonly bool $suggestOptimizations = false,
        private readonly array $optimizationConfig = ['digits' => true, 'word' => true],
    ) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof FuncCall);

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
            return $this->processPregReplaceCallbackArray($patternArg, $scope, $node->getLine());
        }

        $errors = [];
        foreach ($scope->getType($patternArg)->getConstantStrings() as $constantString) {
            $errors = array_merge($errors, $this->validatePattern($constantString->getValue(), $node->getLine()));
        }

        return $errors;
    }

    public function isOptimizationSafe(string $original, string $optimized): bool
    {
        // Extract delimiter, pattern part, and flags for optimized
        $delimiter = $optimized[0] ?? '';
        if ('' === $delimiter) {
            return false; // Invalid
        }

        $lastDelimiterPos = strrpos($optimized, $delimiter);
        if (false === $lastDelimiterPos || 0 === $lastDelimiterPos) {
            return false; // No closing delimiter or empty
        }

        $patternPart = substr($optimized, 1, $lastDelimiterPos - 1);

        // Extract original pattern part
        $originalDelimiter = $original[0] ?? '';
        $originalPatternPart = '';
        if ('' !== $originalDelimiter) {
            $originalLastPos = strrpos($original, $originalDelimiter);
            if (false !== $originalLastPos) {
                $originalPatternPart = substr($original, 1, $originalLastPos - 1);
            }
        }

        // Return false if optimized pattern is empty
        if ('' === $patternPart) {
            return false;
        }

        // Return false if optimized pattern is too short (< 2 chars)
        if (\strlen($patternPart) < 2) {
            return false;
        }

        // Return false if optimized removes newlines that were present in original (unless escaped)
        // Simplified: check if original contains \n and optimized does not
        if (str_contains($originalPatternPart, '\n') && !str_contains($patternPart, '\n')) {
            return false;
        }

        return true;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function processPregReplaceCallbackArray(Node $arrayNode, Scope $scope, int $lineNumber): array
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
            $errors = array_merge($errors, $this->validatePattern($pattern, $lineNumber));
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validatePattern(string $pattern, int $lineNumber): array
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

        try {
            $ast = $this->getRegex()->parse($pattern);
            $ast->accept($this->getValidator());
        } catch (LexerException|ParserException|SyntaxErrorException $e) {
            if ($this->ignoreParseErrors && $this->isLikelyPartialRegexError($e->getMessage())) {
                return [];
            }

            $shortPattern = $this->truncatePattern($pattern);
            $errors[] = RuleErrorBuilder::message(\sprintf('Regex syntax error: %s (Pattern: "%s")', $e->getMessage(), $shortPattern))
                ->line($lineNumber)
                ->identifier($this->getIdentifierForSyntaxError($e->getMessage()))
                ->build();

            return $errors;
        }

        if ($this->reportRedos) {
            try {
                $analysis = $this->getRedosAnalyzer()->analyze($pattern);

                if ($this->exceedsThreshold($analysis->severity)) {
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
            } catch (\Throwable) {
            }
        }

        if ($this->suggestOptimizations) {
            try {
                $ast = $this->getRegex()->parse($pattern);
                $optimizer = new \RegexParser\NodeVisitor\OptimizerNodeVisitor(
                    optimizeDigits: (bool) ($this->optimizationConfig['digits'] ?? true),
                    optimizeWord: (bool) ($this->optimizationConfig['word'] ?? true),
                );
                $optimizedAst = $ast->accept($optimizer);
                // Use compiler to get string back
                $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
                $optimized = $optimizedAst->accept($compiler);
                if ($optimized !== $pattern && \strlen($optimized) < \strlen($pattern)) {
                    // Safeguard: Validate that the optimized pattern is still valid
                    try {
                        $optimizedAst = $this->getRegex()->parse($optimized);
                        $optimizedAst->accept($this->getValidator());
                        // Additional heuristic checks
                        if (!$this->isOptimizationSafe($pattern, $optimized)) {
                            // Optimized pattern is unsafe, do not suggest it
                        } else {
                            // If we reach here, the optimized pattern is valid and safe
                            $shortPattern = $this->truncatePattern($pattern);

                            $errors[] = RuleErrorBuilder::message(\sprintf('Regex pattern can be optimized: "%s"', $shortPattern))
                                ->line($lineNumber)
                                ->identifier(self::IDENTIFIER_OPTIMIZATION)
                                ->tip(\sprintf('Consider using: %s', $optimized))
                                ->build();
                        }
                    } catch (LexerException|ParserException|SyntaxErrorException) {
                        // Optimized pattern is invalid, do not suggest it
                    }
                }
            } catch (\Throwable) {
            }
        }

        try {
            $linter = new \RegexParser\NodeVisitor\LinterNodeVisitor();
            $ast->accept($linter);
            foreach ($linter->getWarnings() as $warning) {
                $tip = $this->getTipForWarning($warning);
                $builder = RuleErrorBuilder::message($warning)
                    ->line($lineNumber)
                    ->identifier('regex.linter');
                if (null !== $tip) {
                    $builder = $builder->tip($tip);
                }
                $errors[] = $builder->build();
            }
        } catch (\Throwable) {
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

    private function exceedsThreshold(ReDoSSeverity $severity): bool
    {
        $currentLevel = match ($severity) {
            ReDoSSeverity::SAFE => 0,
            ReDoSSeverity::LOW => 1,
            ReDoSSeverity::UNKNOWN => 2,
            ReDoSSeverity::MEDIUM => 3,
            ReDoSSeverity::HIGH => 4,
            ReDoSSeverity::CRITICAL => 5,
        };

        $thresholdLevel = match ($this->redosThreshold) {
            'low' => 1,
            'medium' => 3,
            'high' => 4,
            'critical' => 5,
            default => 1,
        };

        return $currentLevel >= $thresholdLevel;
    }

    private function isLikelyPartialRegexError(string $errorMessage): bool
    {
        $indicators = [
            'No closing delimiter',
            'Regex too short',
            'Unknown modifier',
            'Unexpected end',
        ];
        $found = false;
        foreach ($indicators as $indicator) {
            if (false !== stripos($errorMessage, (string) $indicator)) {
                $found = true;

                break;
            }
        }

        return $found;
    }

    private function truncatePattern(string $pattern, int $length = 50): string
    {
        return \strlen($pattern) > $length ? substr($pattern, 0, $length).'...' : $pattern;
    }

    private function getRegex(): Regex
    {
        return $this->regex ??= Regex::create();
    }

    private function getValidator(): ValidatorNodeVisitor
    {
        return $this->validator ??= new ValidatorNodeVisitor();
    }

    private function getRedosAnalyzer(): ReDoSAnalyzer
    {
        return $this->redosAnalyzer ??= new ReDoSAnalyzer();
    }

    private function getTipForWarning(string $warning): ?string
    {
        foreach (self::DOC_LINKS as $key => $url) {
            if (str_contains($warning, $key)) {
                return 'Read more: '.$url;
            }
        }

        return null;
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
