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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;
use RegexParser\ReDoSAnalyzer;
use RegexParser\ReDoSSeverity;

/**
 * Validates regex patterns in preg_* functions.
 *
 * This rule performs two types of checks:
 * 1. Syntax Validation: Ensures the regex is valid PCRE (identifier: regex.syntax).
 * 2. ReDoS Analysis: Checks for catastrophic backtracking vulnerabilities (identifier: regex.redos).
 *
 * @implements Rule<FuncCall>
 */
class PregValidationRule implements Rule
{
    private const array PREG_FUNCTION_MAP = [
        'preg_match' => 0,
        'preg_match_all' => 0,
        'preg_replace' => 0,
        'preg_replace_callback' => 0,
        'preg_split' => 0,
        'preg_grep' => 0,
        'preg_filter' => 0,
    ];

    private const string VALID_DELIMITERS = '/~#%@!();<>';

    /**
     * Map of severity strings to integer levels for comparison.
     */
    private const array SEVERITY_LEVELS = [
        'safe' => 0,
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    private ?Parser $parser = null;

    private ?ValidatorNodeVisitor $validator = null;

    private ?ReDoSAnalyzer $redosAnalyzer = null;

    /**
     * @param string $redosThreshold The minimum severity level to report ('low', 'medium', 'high', 'critical')
     */
    public function __construct(
        private readonly bool $ignoreParseErrors = true,
        private readonly bool $reportRedos = true,
        private readonly string $redosThreshold = 'high',
    ) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        // Optimization: Fast return for non-preg functions
        $functionName = $node->name->toLowerString();
        if (!isset(self::PREG_FUNCTION_MAP[$functionName])) {
            return [];
        }

        $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
        $args = $node->getArgs();

        if (!isset($args[$patternArgPosition])) {
            // Malformed call (missing arguments), handled by native PHPStan rules
            return [];
        }

        $patternArg = $args[$patternArgPosition]->value;
        $patternType = $scope->getType($patternArg);
        $constantStrings = $patternType->getConstantStrings();

        // If we can't determine the string value statically, skip validation
        if (0 === \count($constantStrings)) {
            return [];
        }

        $errors = [];
        foreach ($constantStrings as $constantString) {
            $pattern = $constantString->getValue();

            if (!$this->looksLikeCompleteRegex($pattern)) {
                continue;
            }

            // 1. Syntax Validation
            try {
                $ast = $this->getParser()->parse($pattern);
                // ValidatorNodeVisitor checks for semantic errors (e.g., duplicate group names)
                $ast->accept($this->getValidator());
            } catch (ParserException $e) {
                // Skip validation for likely partial/incomplete patterns if configured
                if ($this->ignoreParseErrors && $this->isLikelyPartialRegexError($e->getMessage())) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf('Regex syntax error: %s', $e->getMessage()))
                    ->line($node->getLine())
                    ->identifier('regex.syntax')
                    ->build();

                // If syntax is invalid, ReDoS analysis is impossible/irrelevant
                continue;
            } catch (\Throwable) {
                // Fail silently on internal errors to avoid crashing PHPStan
                continue;
            }

            // 2. ReDoS Validation
            if ($this->reportRedos) {
                $analysis = $this->getRedosAnalyzer()->analyze($pattern);

                if (!$analysis->isSafe() && $this->exceedsThreshold($analysis->severity)) {
                    $errors[] = RuleErrorBuilder::message(\sprintf(
                        'ReDoS vulnerability detected (%s): %s',
                        strtoupper($analysis->severity->value),
                        $this->truncatePattern($pattern),
                    ))
                        ->line($node->getLine())
                        ->tip(implode("\n", $analysis->recommendations))
                        ->identifier('regex.redos')
                        ->build();
                }
            }
        }

        return $errors;
    }

    private function exceedsThreshold(ReDoSSeverity $severity): bool
    {
        $currentLevel = self::SEVERITY_LEVELS[$severity->value] ?? 0;
        $thresholdLevel = self::SEVERITY_LEVELS[$this->redosThreshold] ?? 3; // Default to 'high'

        return $currentLevel >= $thresholdLevel;
    }

    /**
     * Checks if the pattern looks like a complete regex with valid delimiters.
     */
    private function looksLikeCompleteRegex(string $pattern): bool
    {
        if (\strlen($pattern) < 2) {
            return false;
        }

        $firstChar = $pattern[0];

        if (!str_contains(self::VALID_DELIMITERS, $firstChar)) {
            return false;
        }

        // Simple check: pattern ends with same delimiter or has flags
        // We trust the Parser to do the heavy lifting validation later
        return true;
    }

    private function isLikelyPartialRegexError(string $errorMessage): bool
    {
        $indicators = [
            'No closing delimiter',
            'Regex too short',
            'Unknown modifier',
            'Invalid delimiter',
            'Unexpected end',
        ];

        return array_any($indicators, fn ($indicator) => false !== stripos($errorMessage, (string) $indicator));
    }

    private function truncatePattern(string $pattern, int $length = 50): string
    {
        return \strlen($pattern) > $length ? substr($pattern, 0, $length).'...' : $pattern;
    }

    private function getParser(): Parser
    {
        return $this->parser ??= new Parser();
    }

    private function getValidator(): ValidatorNodeVisitor
    {
        return $this->validator ??= new ValidatorNodeVisitor();
    }

    private function getRedosAnalyzer(): ReDoSAnalyzer
    {
        // Using default compiler instance for analyzer
        return $this->redosAnalyzer ??= new ReDoSAnalyzer();
    }
}
