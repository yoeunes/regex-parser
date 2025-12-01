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

namespace RegexParser\Bridge\Symfony\DataCollector;

use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\Regex;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Data collector for regex patterns used during a request.
 *
 * Implements LateDataCollectorInterface to defer expensive parsing/analysis
 * until after the response is sent. Never crashes the profiler - all errors
 * are captured and displayed instead.
 *
 * @phpstan-type RegexData array{
 *     pattern: string,
 *     source: string,
 *     subject: string|null,
 *     match_result: bool|null,
 *     is_valid: bool,
 *     error: string|null,
 *     explanation: string|null,
 *     score: int,
 *     is_redos_risk: bool,
 *     is_warning: bool,
 * }
 * @phpstan-type CollectorData array{
 *     regexes: array<RegexData>,
 *     total: int,
 *     invalid: int,
 *     redos_risks: int,
 *     warnings: int,
 *     errors: array<string>,
 *     average_complexity: float,
 * }
 */
class RegexCollector extends DataCollector implements LateDataCollectorInterface, ResetInterface
{
    /**
     * @var array<string, CollectedRegex>
     */
    private array $collectedRegexes = [];

    /**
     * @var array<string>
     */
    private array $collectionErrors = [];

    public function __construct(
        private readonly Regex $regex,
        private readonly ExplainNodeVisitor $explainVisitor,
        private readonly ComplexityScoreNodeVisitor $scoreVisitor,
        private readonly int $redosThreshold = 100,
        private readonly int $warningThreshold = 50,
    ) {
        $this->reset();
    }

    /**
     * Collects a regex pattern for later analysis.
     *
     * This method is called during request processing and should be fast.
     * The actual analysis is deferred to lateCollect().
     */
    public function collectRegex(
        string $pattern,
        string $source,
        ?string $subject = null,
        ?bool $matchResult = null,
    ): void {
        try {
            // Use pattern as key to avoid duplicates
            $key = $pattern;

            if (!isset($this->collectedRegexes[$key])) {
                $this->collectedRegexes[$key] = new CollectedRegex(
                    $pattern,
                    $source,
                    $subject,
                    $matchResult,
                );
            }
        } catch (\Throwable $e) {
            $this->collectionErrors[] = \sprintf(
                'Failed to collect regex from %s: %s',
                $source,
                $e->getMessage(),
            );
        }
    }

    /**
     * Called during request handling - resets data for new request.
     */
    #[\Override]
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Data is collected via collectRegex() during request
        // Analysis happens in lateCollect()
    }

    /**
     * Performs expensive parsing/analysis after the response is sent.
     *
     * All logic is wrapped in try/catch to ensure the profiler never crashes.
     */
    #[\Override]
    public function lateCollect(): void
    {
        $regexData = [];
        $invalidCount = 0;
        $redosCount = 0;
        $warningCount = 0;
        $totalScore = 0;
        $analyzedCount = 0;
        $errors = $this->collectionErrors;

        foreach ($this->collectedRegexes as $collected) {
            try {
                $result = $this->analyzePattern($collected);
                $regexData[] = $result;

                if (!$result['is_valid']) {
                    $invalidCount++;
                }
                if ($result['is_redos_risk']) {
                    $redosCount++;
                } elseif ($result['is_warning']) {
                    $warningCount++;
                }
                if ($result['score'] >= 0) {
                    $totalScore += $result['score'];
                    $analyzedCount++;
                }
            } catch (\Throwable $e) {
                // Capture the error and add a minimal entry
                $errorMessage = \sprintf(
                    'Analysis failed for pattern "%s": %s',
                    $this->truncatePattern($collected->pattern),
                    $e->getMessage(),
                );
                $errors[] = $errorMessage;

                $regexData[] = [
                    'pattern' => $collected->pattern,
                    'source' => $collected->source,
                    'subject' => $collected->subject,
                    'match_result' => $collected->matchResult,
                    'is_valid' => false,
                    'error' => $errorMessage,
                    'explanation' => null,
                    'score' => -1,
                    'is_redos_risk' => false,
                    'is_warning' => false,
                ];
                $invalidCount++;
            }
        }

        $averageComplexity = $analyzedCount > 0 ? round($totalScore / $analyzedCount, 2) : 0.0;

        $this->data = [
            'regexes' => $regexData,
            'total' => \count($regexData),
            'invalid' => $invalidCount,
            'redos_risks' => $redosCount,
            'warnings' => $warningCount,
            'errors' => $errors,
            'average_complexity' => $averageComplexity,
        ];
    }

    #[\Override]
    public function getName(): string
    {
        return 'regex_parser';
    }

    #[\Override]
    public function reset(): void
    {
        $this->data = [
            'regexes' => [],
            'total' => 0,
            'invalid' => 0,
            'redos_risks' => 0,
            'warnings' => 0,
            'errors' => [],
            'average_complexity' => 0.0,
        ];
        $this->collectedRegexes = [];
        $this->collectionErrors = [];
    }

    // ========== Accessor Methods for Twig Template ==========

    /**
     * @return CollectorData|Data
     */
    public function getData(): array|Data
    {
        return $this->data;
    }

    public function getTotal(): int
    {
        return $this->getDataValue('total', 0);
    }

    public function getInvalid(): int
    {
        return $this->getDataValue('invalid', 0);
    }

    public function getRedosRisks(): int
    {
        return $this->getDataValue('redos_risks', 0);
    }

    public function getWarnings(): int
    {
        return $this->getDataValue('warnings', 0);
    }

    public function getAverageComplexity(): float
    {
        return $this->getDataValue('average_complexity', 0.0);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->getDataValue('errors', []);
    }

    /**
     * @return array<RegexData>
     */
    public function getRegexes(): array
    {
        return $this->getDataValue('regexes', []);
    }

    /**
     * Returns regexes filtered by severity.
     *
     * @return array<RegexData>
     */
    public function getCriticalRegexes(): array
    {
        return array_filter(
            $this->getRegexes(),
            static fn (array $regex): bool => !$regex['is_valid'] || $regex['is_redos_risk'],
        );
    }

    /**
     * @return array<RegexData>
     */
    public function getWarningRegexes(): array
    {
        return array_filter(
            $this->getRegexes(),
            static fn (array $regex): bool => $regex['is_valid'] && !$regex['is_redos_risk'] && $regex['is_warning'],
        );
    }

    /**
     * @return array<RegexData>
     */
    public function getSafeRegexes(): array
    {
        return array_filter(
            $this->getRegexes(),
            static fn (array $regex): bool => $regex['is_valid'] && !$regex['is_redos_risk'] && !$regex['is_warning'],
        );
    }

    /**
     * Checks if there are any issues (invalid patterns or ReDoS risks).
     */
    public function hasIssues(): bool
    {
        return $this->getInvalid() > 0 || $this->getRedosRisks() > 0;
    }

    /**
     * Analyzes a single pattern and returns structured data.
     *
     * @return RegexData
     */
    private function analyzePattern(CollectedRegex $collected): array
    {
        $isValid = true;
        $error = null;
        $explanation = null;
        $score = 0;

        // Validate the pattern
        try {
            $validation = $this->regex->validate($collected->pattern);
            $isValid = $validation->isValid;
            if (!$isValid) {
                $error = $validation->error ?? 'Invalid pattern';
            }
        } catch (\Throwable $e) {
            $isValid = false;
            $error = 'Validation error: '.$e->getMessage();
        }

        // Parse and analyze (even invalid patterns might parse partially)
        try {
            $ast = $this->regex->parse($collected->pattern);
            $explanation = $ast->accept($this->explainVisitor);
            $score = (int) $ast->accept($this->scoreVisitor);
        } catch (\Throwable $e) {
            $explanation = 'Parse error: '.$e->getMessage();
            $score = -1;
        }

        $isRedosRisk = $score >= $this->redosThreshold;
        $isWarning = !$isRedosRisk && $score >= $this->warningThreshold;

        return [
            'pattern' => $collected->pattern,
            'source' => $collected->source,
            'subject' => $collected->subject,
            'match_result' => $collected->matchResult,
            'is_valid' => $isValid,
            'error' => $error,
            'explanation' => $explanation,
            'score' => $score,
            'is_redos_risk' => $isRedosRisk,
            'is_warning' => $isWarning,
        ];
    }

    /**
     * Truncates a pattern for error messages.
     */
    private function truncatePattern(string $pattern, int $maxLength = 50): string
    {
        if (\strlen($pattern) <= $maxLength) {
            return $pattern;
        }

        return substr($pattern, 0, $maxLength - 3).'...';
    }

    /**
     * Helper to safely get values from data array.
     *
     * @template T
     *
     * @param T $default
     *
     * @return T
     */
    private function getDataValue(string $key, mixed $default): mixed
    {
        if ($this->data instanceof Data) {
            return $default;
        }

        return $this->data[$key] ?? $default;
    }
}
