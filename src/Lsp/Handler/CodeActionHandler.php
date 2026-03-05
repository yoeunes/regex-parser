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

namespace RegexParser\Lsp\Handler;

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Lsp\Document\DocumentManager;
use RegexParser\Lsp\Document\RegexOccurrence;
use RegexParser\Lsp\Protocol\Message;
use RegexParser\Lsp\Protocol\Response;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

/**
 * Handles textDocument/codeAction requests.
 */
final readonly class CodeActionHandler
{
    /**
     * Unicode-related lint IDs that can be fixed by adding /u flag.
     */
    private const UNICODE_LINT_IDS = [
        'regex.lint.unicode.shorthandWithoutU',
        'regex.lint.unicode.propertyWithoutU',
        'regex.lint.unicode.bracedHexWithoutU',
    ];

    public function __construct(
        private DocumentManager $documents,
        private Regex $regex,
    ) {}

    /**
     * Handle textDocument/codeAction request.
     */
    public function handle(Message $message): void
    {
        $params = $message->params ?? [];
        /** @var array<string, mixed> $textDocument */
        $textDocument = $params['textDocument'] ?? [];
        $uri = isset($textDocument['uri']) && \is_string($textDocument['uri']) ? $textDocument['uri'] : null;
        /** @var array{start: array{line: int, character: int}, end: array{line: int, character: int}}|null $range */
        $range = isset($params['range']) && \is_array($params['range']) ? $params['range'] : null;
        /** @var array<string, mixed> $context */
        $context = isset($params['context']) && \is_array($params['context']) ? $params['context'] : [];

        if (null === $message->id || null === $uri || null === $range) {
            Response::success($message->id ?? 0, []);

            return;
        }

        $codeActions = [];

        // Find regex occurrences that overlap with the requested range
        $occurrences = $this->findOccurrencesInRange($uri, $range);

        foreach ($occurrences as $occurrence) {
            // Add Unicode fix actions
            $codeActions = array_merge(
                $codeActions,
                $this->getUnicodeFixActions($occurrence, $uri, $context),
            );

            // Add optimization actions
            $codeActions = array_merge(
                $codeActions,
                $this->getOptimizationActions($occurrence, $uri),
            );
        }

        Response::success($message->id, $codeActions);
    }

    /**
     * Find occurrences that overlap with the given range.
     *
     * @param array{start: array{line: int, character: int}, end: array{line: int, character: int}} $range
     *
     * @return array<RegexOccurrence>
     */
    private function findOccurrencesInRange(string $uri, array $range): array
    {
        $occurrences = [];

        foreach ($this->documents->getOccurrences($uri) as $occurrence) {
            if ($this->rangesOverlap($occurrence->start, $occurrence->end, $range['start'], $range['end'])) {
                $occurrences[] = $occurrence;
            }
        }

        return $occurrences;
    }

    /**
     * Check if two ranges overlap.
     *
     * @param array{line: int, character: int} $start1
     * @param array{line: int, character: int} $end1
     * @param array{line: int, character: int} $start2
     * @param array{line: int, character: int} $end2
     */
    private function rangesOverlap(array $start1, array $end1, array $start2, array $end2): bool
    {
        // Check if range1 ends before range2 starts
        if ($end1['line'] < $start2['line']
            || ($end1['line'] === $start2['line'] && $end1['character'] < $start2['character'])) {
            return false;
        }

        // Check if range2 ends before range1 starts
        if ($end2['line'] < $start1['line']
            || ($end2['line'] === $start1['line'] && $end2['character'] < $start1['character'])) {
            return false;
        }

        return true;
    }

    /**
     * Get code actions for fixing Unicode lint issues.
     *
     * @param array<string, mixed> $context
     *
     * @return array<array<string, mixed>>
     */
    private function getUnicodeFixActions(RegexOccurrence $occurrence, string $uri, array $context): array
    {
        $actions = [];

        // Check if there are Unicode-related diagnostics
        /** @var array<int, array{code?: string}> $diagnostics */
        $diagnostics = isset($context['diagnostics']) && \is_array($context['diagnostics']) ? $context['diagnostics'] : [];
        $hasUnicodeIssue = false;

        foreach ($diagnostics as $diagnostic) {
            $code = $diagnostic['code'] ?? '';
            if (\in_array($code, self::UNICODE_LINT_IDS, true)) {
                $hasUnicodeIssue = true;

                break;
            }
        }

        if (!$hasUnicodeIssue) {
            // Also check by running the linter
            try {
                $ast = $this->regex->parse($occurrence->pattern);
                $linter = new LinterNodeVisitor();
                $ast->accept($linter);

                foreach ($linter->getIssues() as $issue) {
                    if (\in_array($issue->id, self::UNICODE_LINT_IDS, true)) {
                        $hasUnicodeIssue = true;

                        break;
                    }
                }
            } catch (LexerException|ParserException) {
                return [];
            }
        }

        if ($hasUnicodeIssue && !str_contains($occurrence->pattern, '/u')) {
            // Create action to add /u flag
            $newPattern = $this->addUnicodeFlag($occurrence->pattern);

            if (null !== $newPattern) {
                $actions[] = [
                    'title' => 'Add /u flag for Unicode support',
                    'kind' => 'quickfix',
                    'diagnostics' => array_values(array_filter($diagnostics, static fn ($d) => \in_array($d['code'] ?? '', self::UNICODE_LINT_IDS, true))),
                    'isPreferred' => true,
                    'edit' => [
                        'changes' => [
                            $uri => [
                                [
                                    'range' => [
                                        'start' => $occurrence->start,
                                        'end' => $occurrence->end,
                                    ],
                                    'newText' => "'{$newPattern}'",
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        return $actions;
    }

    /**
     * Get code actions for applying optimizations.
     *
     * @return array<array<string, mixed>>
     */
    private function getOptimizationActions(RegexOccurrence $occurrence, string $uri): array
    {
        $actions = [];

        try {
            $result = $this->regex->optimize($occurrence->pattern);

            if ($result->isChanged()) {
                $actions[] = [
                    'title' => 'Apply regex optimization',
                    'kind' => 'refactor.rewrite',
                    'edit' => [
                        'changes' => [
                            $uri => [
                                [
                                    'range' => [
                                        'start' => $occurrence->start,
                                        'end' => $occurrence->end,
                                    ],
                                    'newText' => "'{$result->optimized}'",
                                ],
                            ],
                        ],
                    ],
                ];
            }
        } catch (LexerException|ParserException) {
            // Pattern can't be parsed, skip optimization
        }

        return $actions;
    }

    /**
     * Add /u flag to a regex pattern.
     */
    private function addUnicodeFlag(string $pattern): ?string
    {
        if (\strlen($pattern) < 2) {
            return null;
        }

        $delimiter = $pattern[0];
        $closingDelimiters = ['/' => '/', '#' => '#', '~' => '~', '@' => '@', '!' => '!', '%' => '%',
            '(' => ')', '[' => ']', '{' => '}', '<' => '>'];

        $closingDelimiter = $closingDelimiters[$delimiter] ?? $delimiter;

        // Find the last occurrence of the closing delimiter
        $lastDelimPos = strrpos($pattern, $closingDelimiter);
        if (false === $lastDelimPos || 0 === $lastDelimPos) {
            return null;
        }

        // Extract existing flags
        $flags = substr($pattern, $lastDelimPos + 1);

        // Already has /u
        if (str_contains($flags, 'u')) {
            return null;
        }

        // Add /u flag
        return substr($pattern, 0, $lastDelimPos + 1).'u'.$flags;
    }
}
