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

namespace RegexParser\Bridge\Psalm;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Psalm\CodeLocation;
use Psalm\Issue\PluginIssue;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterFunctionCallAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterFunctionCallAnalysisEvent;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Union;
use RegexParser\Bridge\Psalm\Issue\RegexLinterIssue;
use RegexParser\Bridge\Psalm\Issue\RegexOptimizationIssue;
use RegexParser\Bridge\Psalm\Issue\RegexPatternEmptyIssue;
use RegexParser\Bridge\Psalm\Issue\RegexRedosIssue;
use RegexParser\Bridge\Psalm\Issue\RegexSyntaxIssue;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Exception\SyntaxErrorException;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\ReDoS\ReDoSAnalyzer;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class PregValidationHandler implements AfterFunctionCallAnalysisInterface
{
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

    private static ?PluginConfiguration $configuration = null;

    private static ?Regex $regex = null;

    private static ?ValidatorNodeVisitor $validator = null;

    private static ?ReDoSAnalyzer $redosAnalyzer = null;

    public static function configure(PluginConfiguration $configuration): void
    {
        self::$configuration = $configuration;
    }

    public static function afterFunctionCallAnalysis(AfterFunctionCallAnalysisEvent $event): void
    {
        $functionId = strtolower($event->getFunctionId());
        if (!isset(self::PREG_FUNCTION_MAP[$functionId])) {
            return;
        }

        $args = $event->getExpr()->getArgs();
        $patternPosition = self::PREG_FUNCTION_MAP[$functionId];

        if (!isset($args[$patternPosition])) {
            return;
        }

        $patternArg = $args[$patternPosition]->value;

        if ('preg_replace_callback_array' === $functionId) {
            self::processPregReplaceCallbackArray($patternArg, $event);

            return;
        }

        self::processPatternArgument($patternArg, $event);
    }

    private static function processPregReplaceCallbackArray(Node $arrayNode, AfterFunctionCallAnalysisEvent $event): void
    {
        if (!$arrayNode instanceof Array_) {
            return;
        }

        foreach ($arrayNode->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_) {
                continue;
            }

            self::validatePattern($item->key->value, $event, $item->key);
        }
    }

    private static function processPatternArgument(Node $patternNode, AfterFunctionCallAnalysisEvent $event): void
    {
        $typeProvider = $event->getStatementsSource()->getNodeTypeProvider();
        $type = $typeProvider->getType($patternNode);

        if (null === $type) {
            return;
        }

        $literalStrings = self::getLiteralStrings($type);

        foreach ($literalStrings as $literalString) {
            self::validatePattern($literalString, $event, $patternNode);
        }
    }

    private static function validatePattern(string $pattern, AfterFunctionCallAnalysisEvent $event, Node $patternNode): void
    {
        if ('' === $pattern) {
            self::reportIssue(
                new RegexPatternEmptyIssue('Regex pattern cannot be empty.', new CodeLocation($event->getStatementsSource(), $patternNode)),
                $event,
            );

            return;
        }

        $configuration = self::getConfiguration();

        try {
            $ast = self::getRegex()->parse($pattern);
            $ast->accept(self::getValidator());
        } catch (LexerException|ParserException|SyntaxErrorException $e) {
            if ($configuration->ignoreParseErrors && self::isLikelyPartialRegexError($e->getMessage())) {
                return;
            }

            $message = \sprintf('Regex syntax error: %s (Pattern: "%s")', $e->getMessage(), self::truncatePattern($pattern));

            self::reportIssue(
                new RegexSyntaxIssue($message, new CodeLocation($event->getStatementsSource(), $patternNode)),
                $event,
            );

            return;
        } catch (\Throwable) {
            return;
        }

        if ($configuration->reportRedos) {
            try {
                $analysis = self::getRedosAnalyzer()->analyze($pattern);

                if (self::exceedsThreshold($analysis->severity, $configuration->redosThreshold)) {
                    $message = \sprintf(
                        'ReDoS vulnerability detected (%s): %s',
                        strtoupper($analysis->severity->value),
                        self::truncatePattern($pattern),
                    );

                    if ([] !== $analysis->recommendations) {
                        $message .= ' | Recommendations: '.implode('; ', $analysis->recommendations);
                    }

                    self::reportIssue(
                        new RegexRedosIssue($message, new CodeLocation($event->getStatementsSource(), $patternNode)),
                        $event,
                    );
                }
            } catch (\Throwable) {
            }
        }

        if ($configuration->suggestOptimizations) {
            try {
                $optimized = self::getRegex()->optimize($pattern);
                if ($optimized !== $pattern && \strlen($optimized) < \strlen($pattern)) {
                    $message = \sprintf(
                        'Regex pattern can be optimized: "%s" (Try: %s)',
                        self::truncatePattern($pattern),
                        $optimized,
                    );

                    self::reportIssue(
                        new RegexOptimizationIssue($message, new CodeLocation($event->getStatementsSource(), $patternNode)),
                        $event,
                    );
                }
            } catch (\Throwable) {
            }
        }

        try {
            $linter = new LinterNodeVisitor();
            $ast->accept($linter);
            foreach ($linter->getWarnings() as $warning) {
                self::reportIssue(
                    new RegexLinterIssue('Tip: '.$warning, new CodeLocation($event->getStatementsSource(), $patternNode)),
                    $event,
                );
            }
        } catch (\Throwable) {
        }
    }

    private static function reportIssue(PluginIssue $issue, AfterFunctionCallAnalysisEvent $event): void
    {
        IssueBuffer::maybeAdd($issue, $event->getStatementsSource()->getSuppressedIssues());
    }

    private static function getConfiguration(): PluginConfiguration
    {
        return self::$configuration ??= new PluginConfiguration();
    }

    /**
     * @return list<string>
     */
    private static function getLiteralStrings(Union $type): array
    {
        $strings = [];

        foreach ($type->getAtomicTypes() as $atomic) {
            if ($atomic instanceof TLiteralString) {
                $strings[] = $atomic->value;
            }
        }

        return $strings;
    }

    private static function exceedsThreshold(ReDoSSeverity $severity, string $threshold): bool
    {
        $currentLevel = match ($severity) {
            ReDoSSeverity::SAFE => 0,
            ReDoSSeverity::LOW => 1,
            ReDoSSeverity::UNKNOWN => 2,
            ReDoSSeverity::MEDIUM => 3,
            ReDoSSeverity::HIGH => 4,
            ReDoSSeverity::CRITICAL => 5,
        };

        $thresholdLevel = match ($threshold) {
            'low' => 1,
            'medium' => 3,
            'high' => 4,
            'critical' => 5,
            default => 1,
        };

        return $currentLevel >= $thresholdLevel;
    }

    private static function isLikelyPartialRegexError(string $errorMessage): bool
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

    private static function truncatePattern(string $pattern, int $length = 50): string
    {
        return \strlen($pattern) > $length ? substr($pattern, 0, $length).'...' : $pattern;
    }

    private static function getRegex(): Regex
    {
        return self::$regex ??= Regex::create();
    }

    private static function getValidator(): ValidatorNodeVisitor
    {
        return self::$validator ??= new ValidatorNodeVisitor();
    }

    private static function getRedosAnalyzer(): ReDoSAnalyzer
    {
        return self::$redosAnalyzer ??= new ReDoSAnalyzer();
    }
}
