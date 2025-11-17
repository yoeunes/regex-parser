<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Bundle\DataCollector;

use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\Regex;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @phpstan-property array{regexes?: array<array<string, mixed>>, total?: int, invalid?: int}|Data $data
 */
class RegexCollector extends DataCollector implements LateDataCollectorInterface
{
    /** @var CollectedRegex[] */
    private array $collectedRegexes = [];

    public function __construct(
        private readonly ExplainVisitor $explainVisitor,
        private readonly ComplexityScoreVisitor $scoreVisitor,
    ) {
        // Initialize data to satisfy PHPStan
        $this->data = [
            'regexes' => [],
            'total' => 0,
            'invalid' => 0,
        ];
    }

    public function collectRegex(
        string $pattern,
        string $source,
        ?string $subject = null,
        ?bool $matchResult = null,
    ): void {
        // Avoid collecting duplicates
        if (isset($this->collectedRegexes[$pattern])) {
            return;
        }

        $this->collectedRegexes[$pattern] = new CollectedRegex(
            $pattern,
            $source,
            $subject,
            $matchResult
        );
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Reset data for the request
        $this->data = [
            'regexes' => [],
            'total' => 0,
            'invalid' => 0,
        ];
    }

    public function lateCollect(): void
    {
        $regexData = [];
        $invalidCount = 0;

        foreach ($this->collectedRegexes as $collected) {
            $validation = Regex::validate($collected->pattern);
            if (!$validation->isValid) {
                ++$invalidCount;
            }

            try {
                $ast = Regex::parse($collected->pattern);
                $explanation = $ast->accept($this->explainVisitor);
                $score = $ast->accept($this->scoreVisitor);
            } catch (\Exception $e) {
                $explanation = 'Error: '.$e->getMessage();
                $score = -1;
            }

            $regexData[] = [
                'pattern' => $collected->pattern,
                'source' => $collected->source,
                'subject' => $collected->subject,
                'match_result' => $collected->matchResult,
                'validation' => $validation,
                'explanation' => $explanation,
                'score' => $score,
            ];
        }

        $this->data = [
            'regexes' => $regexData,
            'total' => \count($regexData),
            'invalid' => $invalidCount,
        ];
    }

    public function getName(): string
    {
        return 'regex_parser.collector';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->collectedRegexes = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if (!\is_array($this->data)) {
            return [];
        }

        return $this->data;
    }

    public function getTotal(): int
    {
        if (!\is_array($this->data)) {
            return 0;
        }

        return $this->data['total'] ?? 0;
    }

    public function getInvalid(): int
    {
        if (!\is_array($this->data)) {
            return 0;
        }

        return $this->data['invalid'] ?? 0;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getRegexes(): array
    {
        if (!\is_array($this->data)) {
            return [];
        }

        return $this->data['regexes'] ?? [];
    }
}
