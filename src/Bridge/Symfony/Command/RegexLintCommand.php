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

namespace RegexParser\Bridge\Symfony\Command;

use RegexParser\Bridge\Symfony\Console\LinkFormatter;
use RegexParser\Bridge\Symfony\Console\RelativePathHelper;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
use RegexParser\Bridge\Symfony\Service\RouteValidationService;
use RegexParser\Bridge\Symfony\Service\ValidatorValidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'regex:lint',
    description: 'Lints, validates, and optimizes regex patterns in your PHP code.',
)]
final class RegexLintCommand extends Command
{
    private readonly RelativePathHelper $pathHelper;
    private readonly LinkFormatter $linkFormatter;

    public function __construct(
        private readonly RegexAnalysisService $analysis,
        private readonly ?RouteValidationService $routeValidation = null,
        private readonly ?ValidatorValidationService $validatorValidation = null,
        ?string $editorUrl = null,
    ) {
        $this->pathHelper = new RelativePathHelper(getcwd() ?: null);
        $this->linkFormatter = new LinkFormatter($editorUrl, $this->pathHelper);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The paths to analyze', ['src'])
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Paths to exclude', ['vendor'])
            ->addOption('min-savings', null, InputOption::VALUE_OPTIONAL, 'Minimum optimization savings in characters', 1)
            ->addOption('no-routes', null, InputOption::VALUE_NONE, 'Skip route validation')
            ->addOption('no-validators', null, InputOption::VALUE_NONE, 'Skip validator validation')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command scans your PHP code for regex patterns and provides:

* Validation of regex syntax
* Performance and security warnings  
* Optimization suggestions
* Integration with Symfony routes and validators

<info>php %command.full_name%</info>

Analyze specific directories:
<info>php %command.full_name% src/ lib/</info>

Exclude directories:
<info>php %command.full_name% --exclude=tests --exclude=vendor</info>

Show only significant optimizations:
<info>php %command.full_name% --min-savings=10</info>

Skip specific validations:
<info>php %command.full_name% --no-routes --no-validators</info>
EOF
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $paths = $input->getArgument('paths');
        $exclude = $input->getOption('exclude');
        $minSavings = (int) $input->getOption('min-savings');
        $skipRoutes = $input->getOption('no-routes');
        $skipValidators = $input->getOption('no-validators');

        $this->showBanner($io);

        try {
            $patterns = $this->analysis->scan($paths, $exclude);
        } catch (\Throwable $e) {
            $io->error("Failed to scan files: {$e->getMessage()}");
            return Command::FAILURE;
        }

        if (empty($patterns)) {
            $this->renderSummary($io, $this->createStats(), isEmpty: true);
            return Command::SUCCESS;
        }

        $stats = $this->createStats();
        $allResults = $this->analyzePatterns($patterns, $io, $minSavings);

        if (!$skipRoutes && $this->routeValidation) {
            $allResults = [...$allResults, ...$this->analyzeRoutes()];
        }

        if (!$skipValidators && $this->validatorValidation) {
            $allResults = [...$allResults, ...$this->analyzeValidators()];
        }

        if (!empty($allResults)) {
            usort($allResults, fn ($a, $b) => $this->getSeverityScore($b) <=> $this->getSeverityScore($a));
            $this->displayResults($io, $allResults);
            $stats = $this->calculateStats($stats, $allResults);
        }

        $exitCode = $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        $this->renderSummary($io, $stats);

        return $exitCode;
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Regex Parser</> <fg=gray>linting...</>');
        $io->newLine();
    }

    private function createStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    private function analyzePatterns(array $patterns, SymfonyStyle $io, int $minSavings): array
    {
        if (empty($patterns)) {
            return [];
        }

        $io->progressStart(count($patterns));
        
        $issues = $this->analysis->lint($patterns, fn () => $io->progressAdvance());
        $optimizations = $this->analysis->suggestOptimizations($patterns, $minSavings);
        
        $io->progressFinish();

        return $this->combineResults($issues, $optimizations, $patterns);
    }

    private function analyzeRoutes(): array
    {
        if (!$this->routeValidation) {
            return [];
        }

        $issues = $this->routeValidation->analyze();
        return $this->convertAnalysisIssuesToResults($issues, 'Symfony Router');
    }

    private function analyzeValidators(): array
    {
        if (!$this->validatorValidation) {
            return [];
        }

        $issues = $this->validatorValidation->analyze();
        return $this->convertAnalysisIssuesToResults($issues, 'Symfony Validator');
    }

    private function displayResults(SymfonyStyle $io, array $results): void
    {
        $this->outputIntegratedResults($io, $results);
    }

    private function calculateStats(array $stats, array $results): array
    {
        return $this->updateStatsFromResults($stats, $results);
    }

    private function outputIntegratedResults(SymfonyStyle $io, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $groupedByFile = $this->groupResultsByFile($results);

        foreach ($groupedByFile as $file => $fileResults) {
            $this->renderFileHeader($io, $file);
            array_walk($fileResults, fn ($result) => $this->renderResultCard($io, $result));
        }
    }

    private function groupResultsByFile(array $results): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $grouped[$result['file']][] = $result;
        }
        return $grouped;
    }

    private function renderFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln(\sprintf('  <fg=white;bg=gray;options=bold> %s </>', $relPath));
    }

    private function renderResultCard(SymfonyStyle $io, array $result): void
    {
        $this->displayPatternContext($io, $result);
        $this->displayIssues($io, $result['issues'] ?? []);
        $this->displayOptimizations($io, $result['optimizations'] ?? []);
        $io->newLine();
    }

    private function displayPatternContext(SymfonyStyle $io, array $result): void
    {
        $pattern = $this->extractPatternForResult($result);
        $line = $result['line'];
        $file = $result['file'];

        if ($pattern) {
            $highlighted = $this->safelyHighlightPattern($pattern);
            $io->writeln(sprintf('  <fg=gray>%d:</> %s', $line, $highlighted));
        } else {
            $link = $this->linkFormatter->format($file, $line, 'line '.$line, 1, (string) $line);
            $io->writeln(sprintf('  <fg=gray>%s:</>', $link));
        }
    }

    private function safelyHighlightPattern(string $pattern): string
    {
        try {
            return $this->analysis->highlight(OutputFormatter::escape($pattern));
        } catch (\Exception) {
            return OutputFormatter::escape($pattern);
        }
    }

    private function displayIssues(SymfonyStyle $io, array $issues): void
    {
        foreach ($issues as $issue) {
            $badge = $this->getIssueBadge($issue['type']);
            $this->displaySingleIssue($io, $badge, $issue['message']);

            if (!empty($issue['hint'])) {
                $io->writeln(sprintf('         <fg=gray>↳ %s</>', $issue['hint']));
            }
        }
    }

    private function getIssueBadge(string $type): string
    {
        return match ($type) {
            'error' => '<bg=red;fg=white;options=bold> FAIL </>',
            'warning' => '<bg=yellow;fg=black;options=bold> WARN </>',
            default => '<bg=gray;fg=white;options=bold> INFO </>',
        };
    }

    private function displayOptimizations(SymfonyStyle $io, array $optimizations): void
    {
        foreach ($optimizations as $opt) {
            $io->writeln('    <bg=blue;fg=white;options=bold> FIX </> <fg=blue;options=bold>Optimization available</>');

            $original = $this->safelyHighlightPattern($opt['optimization']->original);
            $optimized = $this->safelyHighlightPattern($opt['optimization']->optimized);

            $io->writeln(sprintf('         <fg=red>- %s</>', $original));
            $io->writeln(sprintf('         <fg=green>+ %s</>', $optimized));
        }
    }

    private function displaySingleIssue(SymfonyStyle $io, string $badge, string $message): void
    {
        // Split message by newline to handle carets/pointers correctly
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines);

        // Print the primary error message on the same line as the badge
        $io->writeln(\sprintf('    %s <fg=white>%s</>', $badge, $firstLine));

        // Print subsequent lines (like regex pointers ^) with indentation preserved
        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $io->writeln(\sprintf('         <fg=gray>%s %s</>', 0 === $index ? '↳' : ' ', $this->stripMessageLine($line)));
            }
        }
    }

    private function renderSummary(SymfonyStyle $io, array $stats, bool $isEmpty = false): void
    {
        $io->newLine();

        if ($isEmpty) {
            $io->writeln('  <bg=green;fg=white;options=bold> PASS </> <fg=gray>No regex patterns found.</>');
            $this->showFooter($io);
            return;
        }

        $this->showSummaryMessage($io, $stats);
        $this->showFooter($io);
    }

    private function showSummaryMessage(SymfonyStyle $io, array $stats): void
    {
        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $optimizations = $stats['optimizations'];

        $message = match (true) {
            $errors > 0 => sprintf(
                '  <bg=red;fg=white;options=bold> FAIL </> <fg=red;options=bold>%d invalid patterns</><fg=gray>, %d warnings, %d optimizations.</>',
                $errors, $warnings, $optimizations
            ),
            $warnings > 0 => sprintf(
                '  <bg=yellow;fg=black;options=bold> PASS </> <fg=yellow;options=bold>%d warnings found</><fg=gray>, %d optimizations available.</>',
                $warnings, $optimizations
            ),
            default => sprintf(
                '  <bg=green;fg=white;options=bold> PASS </> <fg=green;options=bold>No issues found</><fg=gray>, %d optimizations available.</>',
                $optimizations
            ),
        };

        $io->writeln($message);
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
    }

    private function combineResults(array $issues, array $optimizations, array $originalPatterns): array
    {
        $patternMap = $this->createPatternMap($originalPatterns);
        $results = [];

        $this->addIssuesToResults($issues, $patternMap, $results);
        $this->addOptimizationsToResults($optimizations, $patternMap, $results);

        return array_values($results);
    }

    private function createPatternMap(array $originalPatterns): array
    {
        $map = [];
        foreach ($originalPatterns as $pattern) {
            $key = $pattern->file.':'.$pattern->line;
            $map[$key] = $pattern->pattern;
        }
        return $map;
    }

    private function addIssuesToResults(array $issues, array $patternMap, array &$results): void
    {
        foreach ($issues as $issue) {
            if ($this->shouldIgnoreIssue($issue)) {
                continue;
            }

            $key = $issue['file'].':'.$issue['line'];
            $results[$key] ??= $this->createResultStructure($issue, $patternMap[$key] ?? null);
            $results[$key]['issues'][] = $issue;
        }
    }

    private function addOptimizationsToResults(array $optimizations, array $patternMap, array &$results): void
    {
        foreach ($optimizations as $opt) {
            $key = $opt['file'].':'.$opt['line'];
            $pattern = $patternMap[$key] ?? $opt['optimization']->original ?? null;
            
            $results[$key] ??= $this->createResultStructure($opt, $pattern);
            $results[$key]['optimizations'][] = $opt;
        }
    }

    private function shouldIgnoreIssue(array $issue): bool
    {
        $content = @file_get_contents($issue['file']);
        if (false === $content) {
            return false;
        }

        $lines = explode("\n", $content);
        $prevLineIndex = $issue['line'] - 2;
        
        return $prevLineIndex >= 0 
            && isset($lines[$prevLineIndex]) 
            && str_contains($lines[$prevLineIndex], '// @regex-lint-ignore');
    }

    private function createResultStructure(array $item, ?string $pattern): array
    {
        return [
            'file' => $item['file'],
            'line' => $item['line'],
            'pattern' => $pattern,
            'issues' => [],
            'optimizations' => [],
        ];
    }

    private function extractPatternForResult(array $result): ?string
    {
        if (!empty($result['pattern'])) {
            return $result['pattern'];
        }

        if (!empty($result['issues'])) {
            $firstIssue = $result['issues'][0];
            if (!empty($firstIssue['pattern'])) {
                return $firstIssue['pattern'];
            }
            if (!empty($firstIssue['regex'])) {
                return $firstIssue['regex'];
            }
        }

        if (!empty($result['optimizations'])) {
            $firstOpt = $result['optimizations'][0];
            if (isset($firstOpt['optimization']->original)) {
                return $firstOpt['optimization']->original;
            }
        }

        return null;
    }

    private function convertAnalysisIssuesToResults(array $issues, string $category): array
    {
        return array_map(
            fn ($issue, $index) => $this->convertAnalysisIssueToResult($issue, $index, $category),
            $issues,
            array_keys($issues)
        );
    }

    private function convertAnalysisIssueToResult($issue, int $index, string $category): array
    {
        [$file, $location] = $this->extractFileAndLocation($issue->id ?? null, $category);
        [$pattern, $message] = $this->extractPatternAndMessage($issue->pattern, $issue->message, $location);

        return [
            'file' => $file,
            'line' => $index + 1,
            'pattern' => $pattern,
            'location' => $location,
            'issues' => [
                [
                    'type' => $issue->isError ? 'error' : 'warning',
                    'message' => $message,
                    'file' => $file,
                    'line' => $index + 1,
                ],
            ],
            'optimizations' => [],
        ];
    }

    private function extractFileAndLocation(?string $id, string $category): array
    {
        if (!$id) {
            return [$category, null];
        }

        if (str_contains($id, ' (Route: ')) {
            [$file, $route] = explode(' (Route: ', $id, 2);
            return [$file, 'Route: '.rtrim($route, ')')];
        }

        return [$category, $id];
    }

    private function extractPatternAndMessage(?string $pattern, string $message, ?string $location): array
    {
        if (!$pattern && preg_match('/pattern: ([^)]+)/', $message, $matches)) {
            $pattern = trim($matches[1], '#');
            $message = preg_replace('/ \(pattern: [^)]+\)/', '', $message);
        }

        if (!$location && preg_match('/Route "([^"]+)"/', (string) $message, $matches)) {
            $message = preg_replace('/Route "[^"]+" /', '', (string) $message);
        }

        return [$pattern, $message];
    }

    private function updateStatsFromResults(array $stats, array $results): array
    {
        foreach ($results as $result) {
            $stats['errors'] += count(array_filter($result['issues'], fn ($issue) => 'error' === $issue['type']));
            $stats['warnings'] += count(array_filter($result['issues'], fn ($issue) => 'warning' === $issue['type']));
            $stats['optimizations'] += count($result['optimizations']);
        }

        return $stats;
    }

    private function getSeverityScore(array $result): int
    {
        $issueScore = array_reduce(
            $result['issues'],
            fn ($carry, $issue) => $carry + match ($issue['type']) {
                'error' => 100,
                'warning' => 10,
                default => 0,
            },
            0
        );

        return $issueScore + count($result['optimizations']);
    }

    private function stripMessageLine(string $message): string
    {
        return preg_replace_callback(
            '/^Line \d+:/m',
            fn ($matches) => str_repeat(' ', strlen($matches[0])),
            $message
        );
    }
}
