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

namespace RegexParser\Cli\Command;

use RegexParser\Cli\AbstractCommand;
use RegexParser\Regex;
use RegexParser\Cli\Output;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\ReDoS\ReDoSMode;
use RegexParser\ReDoS\ReDoSConfirmOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interactive regex testing command.
 */
final class TestCommand extends AbstractCommand
{
    private const MAX_HISTORY_SIZE = 50;

    private ?Regex $regex = null;
    private ?string $currentPattern = null;
    private ?string $currentFlags = '';

    protected function configure(): void
    {
        $this
            ->setName('test')
            ->setDescription('Interactive regex pattern tester and debugger')
            ->setHelp('Test regex patterns interactively with explanations and ReDoS analysis')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->getBanner());

        $output->writeln([
            '',
            '<fg=cyan;options>RegexParser Interactive Tester</>',
            '',
            'Type a pattern to test, or commands below:',
            '',
            '  <fg=green;help</>              Show this help message',
            '  <fg=green;quit</> or <fg=green;exit</> or <fg=green>Ctrl+D</>   Exit tester',
            '',
            'Available commands:',
            '  <fg=yellow>set-flags</>      Set pattern flags (e.g., <fg=cyan>i</>, <fg=cyan>u</>)',
            '  <fg=yellow>pattern</>         Show or set current pattern',
            '  <fg=yellow>explain</>         Explain current pattern',
            '  <fg=yellow>highlight</>       Highlight current pattern',
            '  <fg=yellow>redos</>          Check ReDoS risk',
            '  <fg=yellow>analyze</>         Full analysis of current pattern',
            '',
            'Examples:',
            '  <fg=cyan>^\d+$</>              Set pattern and test',
            '  <fg=cyan>explain ^\d+$</>      Explain pattern',
            '  <fg=cyan>redos ^\d+$</>          Check ReDoS',
            '',
            'TIP: Press <fg=green>Tab</> to auto-complete patterns from history',
        ]);

        $output->writeln('');

        $regex = Regex::create();
        $cursor = new Cursor($output);

        $history = [];
        $historyFile = $this->getHistoryFile();
        if (file_exists($historyFile)) {
            $lines = file($historyFile);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ('' !== $trimmed) {
                    $history[] = $trimmed;
                }
            }
        }

        $output->writeln('');

        while (true) {
            try {
                $question = new Question(
                    'regex> ',
                    $this->currentPattern ?? '/pattern/',
                );
                $question->setAutocompleterCallback(function (string $userInput) use ($history) {
                    return $this->autocompleteFromHistory($userInput, $history);
                });

                $answer = $cursor->askQuestion($question);

                if (null === $answer || '' === $answer) {
                    continue;
                }

                $trimmedAnswer = trim($answer);

                if ($this->isCommand($trimmedAnswer)) {
                    $this->executeCommand($trimmedAnswer, $output, $cursor, $history);
                    continue;
                }

                if ($this->setPattern($trimmedAnswer)) {
                    $this->addToHistory($trimmedAnswer, $history);
                    continue;
                }

                if ($this->setFlags($trimmedAnswer)) {
                    $this->addToHistory($trimmedAnswer, $history);
                    continue;
                }

                $testPattern = $this->buildTestPattern($trimmedAnswer, $this->currentFlags);
                $this->testPattern($testPattern, $output);

                $this->addToHistory($trimmedAnswer, $history);
            } catch (\Throwable $e) {
                $output->writeln([
                    '',
                    '<fg=red>Error</>: ' . $e->getMessage(),
                    '',
                    '<fg=yellow>Command</>: ' . $e->getMessage(),
                ]);

                $output->writeln('');
            }
        }

        return 0;
    }

    private function isCommand(string $input): bool
    {
        return in_array(strtolower($input), [
            'help', 'h', 'exit', 'quit', 'q',
            'set-flags', 'flags', 'f',
            'pattern', 'p',
            'explain', 'e',
            'highlight', 'hl',
            'redos', 'r',
            'analyze', 'a',
            'clear', 'cls',
        ]);
    }

    private function setPattern(string $input): bool
    {
        if (!str_starts_with($input, 'set-') && !str_starts_with($input, 'set-pattern')) {
            return false;
        }

        $parts = explode(' ', $input, 3);
        if (count($parts) < 2) {
            $this->currentPattern = null;
            return true;
        }

        $pattern = trim(implode(' ', array_slice($parts, 1)));

        if ($pattern === '') {
            $output = $this->getOutput();
            $output->writeln(['<fg=yellow>Pattern cleared.</>']);

            return true;
        }

        $this->currentPattern = $this->addDelimiters($pattern);

        $output = $this->getOutput();
        $output->writeln([
            '',
            '<fg=green>Pattern set:</> ' . $output->escape($this->currentPattern),
        ]);

        return true;
    }

    private function setFlags(string $input): bool
    {
        if (!str_starts_with($input, 'set-') && !str_starts_with($input, 'set-flags')) {
            return false;
        }

        $parts = explode(' ', $input);
        array_shift($parts);
        $flags = trim(implode(' ', $parts));

        if ($flags === '') {
            $output = $this->getOutput();
            $output->writeln(['<fg=yellow>Flags cleared.</>']);

            $this->currentFlags = '';
            return true;
        }

        $this->currentFlags = $flags;

        $output = $this->getOutput();
        $output->writeln([
            '',
            '<fg=green>Flags set:</> ' . $output->escape($this->currentFlags),
        ]);

        return true;
    }

    private function executeCommand(string $command, OutputInterface $output, Cursor $cursor, array &$history): void
    {
        if ($this->currentPattern === null) {
            $output->writeln(['<fg=yellow>No pattern set. Use <fg=cyan>set-pattern</> first.</>']);
            return;
        }

        $output = $this->getOutput();

        switch (strtolower($command)) {
            case 'help':
            case 'h':
                $this->showTestHelp($output);
                break;

            case 'explain':
            case 'e':
                $explanation = $regex->explain($this->currentPattern);
                $output->writeln(['']);
                $output->writeln([
                    '',
                    '<fg=cyan>Pattern:</> ' . $output->escape($this->currentPattern),
                    '',
                    '<fg=green>Explanation:</>',
                    '',
                    $explanation,
                ]);
                break;

            case 'highlight':
            case 'hl':
                $highlighted = $regex->highlight($this->currentPattern, 'console');
                $output->writeln(['']);
                $output->writeln([
                    '',
                    '<fg=cyan>Pattern:</> ' . $output->escape($this->currentPattern),
                    '',
                    '<fg=green>Highlighting:</>',
                    '',
                    $highlighted,
                ]);
                break;

            case 'redos':
            case 'r':
                $analysis = $regex->redos($this->currentPattern);
                $output->writeln(['']);

                $output->writeln([
                    '',
                    '<fg=cyan>Pattern:</> ' . $output->escape($this->currentPattern),
                    '',
                    '<fg=green>ReDoS Risk:</> ' . $output->escape($analysis->severity->value),
                    '',
                    '<fg=green>Severity Score:</> ' . $analysis->score,
                    '',
                '<fg=yellow>Findings:</> ' . count($analysis->findings) . ' risk(s) detected',
                ]);

                if (!empty($analysis->findings)) {
                    foreach ($analysis->findings as $finding) {
                        $output->writeln([
                            "  <fg=yellow>{$finding->message}</>",
                            '',
                            '  <fg=dim>Position: ' . $finding->position,
                        ]);

                        if ($finding->suggestedRewrite !== null) {
                            $output->writeln([
                                "  <fg=green>Suggested:</> " . $output->escape($finding->suggestedRewrite),
                            ]);
                        }
                    }
                }

                $output->writeln('');
                break;

            case 'analyze':
            case 'a':
                $report = $regex->analyze($this->currentPattern);
                $output->writeln(['']);

                $output->writeln([
                    '',
                    '<fg=cyan>Pattern:</> ' . $output->escape($this->currentPattern),
                    '',
                    '<fg=green>Valid:</> ' . ($report->isValid ? 'Yes' : 'No'),
                    '',
                    '<fg=green>Complexity:</> ' . $report->complexityScore ?? 'N/A',
                    '',
                    '<fg=green>ReDoS Risk:</> ' . ($report->redos ? $report->redos->severity->value : 'N/A'),
                ]);

                if (!$report->isValid && $report->error !== null) {
                    $output->writeln([
                        '',
                        '<fg=red>Error:</> ' . $output->escape($report->error),
                    ]);
                }

                if (!empty($report->lintIssues)) {
                    $output->writeln([
                        '',
                        '<fg=yellow>Lint Issues:</>',
                        '',
                    ]);

                    foreach ($report->lintIssues as $issue) {
                        $output->writeln([
                            "  <fg=yellow>{$issue->message}</>",
                        ]);
                    }
                }

                $output->writeln('');
                break;

            case 'clear':
            case 'cls':
                $output->clear();
                $output->writeln(['<fg=green>Screen cleared.</>']);
                break;

            default:
                $output->writeln(['<fg=red>Unknown command:</> ' . $output->escape($command)]);
                $output->writeln(['<fg=yellow>Type <fg=cyan>help</> for available commands.</>']);
                break;
        }
    }

    private function testPattern(string $pattern, OutputInterface $output): void
    {
        $output->writeln(['']);

        $output->writeln([
            '<fg=cyan>Testing pattern:</> ' . $output->escape($pattern),
        ]);

        if ($this->currentFlags !== '') {
            $output->writeln([
                '<fg=dim>Flags:</> ' . $output->escape($this->currentFlags),
            ]);
        }

        $output->writeln('');

        $validation = $regex->validate($pattern);
        $result = $this->isValid();

        if (!$validation) {
            $output->writeln([
                '',
                '<fg=red>Invalid pattern</>',
                '',
                '<fg=red>Error:</> ' . $output->escape($validation->error ?? 'Unknown error'),
            ]);

            if (null !== $validation->hint && $validation->hint !== '') {
                $output->writeln([
                    '<fg=yellow>Hint:</> ' . $output->escape($validation->hint),
                ]);
            }
        } else {
            $output->writeln([
                '<fg=green>Pattern is valid ✓</>',
                '',
                '<fg=green>Complexity Score:</> ' . ($validation->complexityScore ?? 'N/A'),
            ]);

            $redos = $regex->redos($pattern, ReDoSSeverity::MEDIUM, ReDoSMode::THEORETICAL);
            $severityOrder = [ReDoSSeverity::SAFE, ReDoSSeverity::LOW, ReDoSSeverity::MEDIUM, ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL];
            $riskLevel = array_search($redos->severity, $severityOrder, true);

            if ($riskLevel >= 2) {  // MEDIUM or worse
                $output->writeln([
                    '<fg=yellow>ReDoS Risk:</> ' . $output->escape($redos->severity->value),
                ]);
            } else {
                $output->writeln([
                    '<fg=green>ReDoS Risk:</> ' . $output->escape($redos->severity->value),
                ]);
            }

            if (!empty($redos->findings)) {
                $output->writeln('');
                $output->writeln([
                    '<fg=yellow>Risks detected:</>',
                    '',
                ]);

                foreach ($redos->findings as $finding) {
                    $output->writeln([
                        "  <fg=red>{$finding->message}</>",
                        '',
                        '  <fg=dim>Position: ' . $finding->position,
                    ]);

                    if ($finding->suggestedRewrite !== null) {
                        $output->writeln([
                            "  <fg=green>Suggested:</> " . $output->escape($finding->suggestedRewrite),
                        ]);
                    }
                }
            }
        }
    }

    private function isValid(): bool
    {
        try {
            $validation = $regex->validate($this->currentPattern ?? '');
            return $validation->isValid ?? false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function addToHistory(string $item, array &$history): void
    {
        array_unshift($history, $item);
        if (count($history) > self::MAX_HISTORY_SIZE) {
            $history = array_slice($history, 0, self::MAX_HISTORY_SIZE);
        }

        $this->saveHistory($history);
    }

    private function saveHistory(array $history): void
    {
        $historyFile = $this->getHistoryFile();

        $dir = dirname($historyFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $content = implode("\n", $history);
        file_put_contents($historyFile, $content);

        $stats = [
            'hits' => $stats ?? ['hits' => 0],
            'misses' => $stats ?? ['misses' => 0],
        ];
        $stats['hits'] = count($history);

        $statsFile = dirname($historyFile) . '/.regex_stats';
        file_put_contents($statsFile, json_encode($stats, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    private function getHistoryFile(): string
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE') ?: '/tmp';
        return $homeDir . '/.regex_history';
    }

    private function autocompleteFromHistory(string $input, array $history): array
    {
        $history = array_unique($history);
        $matches = [];

        foreach ($history as $item) {
            if (str_starts_with($item, $input)) {
                $matches[] = substr($item, strlen($input));
            }
        }

        return $matches;
    }

    private function buildTestPattern(string $input, string $flags): string
    {
        $pattern = trim($input);

        $delimiters = ['/', '#', '~', '%', '@', '!'];
        $hasDelimiter = false;

        foreach ($delimiters as $delim) {
            if (str_starts_with($pattern, $delim) && str_ends_with($pattern, $delim)) {
                $hasDelimiter = true;
                break;
            }
        }

        if ($hasDelimiter) {
            return $pattern;
        }

        if ('' !== $pattern && !str_starts_with($pattern, '/')) {
            return '/' . $pattern . '/' . $flags;
        }

        return '/' . $pattern . '/';
    }

    private function addDelimiters(string $pattern): string
    {
        $delimiters = ['/', '#', '~', '%', '@', '!'];

        foreach ($delimiters as $delim) {
            if (str_starts_with($pattern, $delim) || str_starts_with($pattern, "'") || str_starts_with($pattern, '"')) {
                return $pattern;
            }
        }

        return '/' . $pattern . '/';
    }

    private function showTestHelp(OutputInterface $output): void
    {
        $output->writeln([
            '<fg=cyan>Test Commands:</>',
            '',
            '  <fg=green;set-pattern</> <pattern>       Set regex pattern to test',
            '  <fg=green;set-flags</> <flags>         Set pattern flags (e.g., i, u, x)',
            '',
            '  <fg=green>pattern</>                  Show current pattern',
            '  <fg=green>flags</>                   Show current flags',
            '  <fg=green>explain</>                 Explain current pattern',
            '  <fg=green>highlight</>               Highlight current pattern',
            '  <fg=green>redos</>                   Check ReDoS risk',
            '  <fg=green>analyze</>                 Full analysis of pattern',
            '',
            '<fg=cyan>Examples:</>',
            '',
            '  <fg=yellow>^\d+$</>                    Test with digits',
            '  <fg=yellow>explain ^\d+$</>            Explain the pattern',
            '  <fg=yellow>redos ^\d+$</>              Check ReDoS risk',
            '',
            '<fg=cyan>Pattern Building:</>',
            '',
            '  Patterns are automatically wrapped in delimiters (/)',
            '  Press <fg=green>Tab</> to auto-complete from history',
            '  Press <fg=green>Ctrl+C</> or type <fg=green>quit</> to exit',
        ]);
    }

    private function getBanner(): string
    {
        return [
            '',
            '<fg=cyan>RegexParser v' . Regex::VERSION . '</> <fg=green>Interactive Tester</>',
            'PHP ' . \PHP_VERSION,
        ];
    }
}
