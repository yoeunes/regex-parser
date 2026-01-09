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

namespace RegexParser\Bridge\Symfony\Analyzer\Formatter;

use RegexParser\Bridge\Symfony\Analyzer\AnalysisNotice;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisReport;
use RegexParser\Bridge\Symfony\Analyzer\IssueDetail;
use RegexParser\Bridge\Symfony\Analyzer\ReportSection;
use RegexParser\Bridge\Symfony\Analyzer\Severity;
use RegexParser\Regex;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
final readonly class ConsoleReportFormatter
{
    private const ARROW_LABEL = "\u{21B3}";
    private const BADGE_CRIT = '<bg=red;fg=white;options=bold> CRIT </>';
    private const BADGE_FAIL = '<bg=red;fg=white;options=bold> FAIL </>';
    private const BADGE_WARN = '<bg=yellow;fg=black;options=bold> WARN </>';
    private const BADGE_PASS = '<bg=green;fg=white;options=bold> PASS </>';

    public function render(AnalysisReport $report, SymfonyStyle $io, bool $showBanner = true, bool $debug = false): void
    {
        if ($showBanner) {
            $this->showBanner($io);
        }

        foreach ($report->sections as $section) {
            $this->renderSection($io, $section, $debug);
        }

        if ($showBanner) {
            $this->showFooter($io);
        }
    }

    private function renderSection(SymfonyStyle $io, ReportSection $section, bool $debug): void
    {
        $io->section($section->title);

        $this->renderNotices($io, $section->warnings);
        $this->renderMeta($io, $section->meta);
        $this->renderNotices($io, $section->summary);

        foreach ($section->issues as $issue) {
            $io->writeln('  '.$this->badge($issue->severity).' <fg=white>'.$issue->title.'</>');

            foreach ($issue->details as $detail) {
                $value = $this->formatDetailValue($detail);
                $io->writeln('      <fg=gray>'.self::ARROW_LABEL.' '.$detail->label.':</> '.$value);
            }

            foreach ($issue->notes as $note) {
                $io->writeln('      <fg=gray>'.self::ARROW_LABEL.' Note:</> '.$note);
            }

            $io->newLine();
        }

        if ([] !== $section->suggestions) {
            $io->section('Suggestions');
            foreach ($section->suggestions as $suggestion) {
                $io->writeln('  <fg=gray>'.self::ARROW_LABEL.'</> '.$suggestion);
            }
            $io->newLine();
        }

        if ($debug && [] !== $section->debug) {
            $io->section('Debug');
            $this->renderMeta($io, $section->debug);
        }
    }

    /**
     * @param array<int, AnalysisNotice> $notices
     */
    private function renderNotices(SymfonyStyle $io, array $notices): void
    {
        if ([] === $notices) {
            return;
        }

        foreach ($notices as $notice) {
            $io->writeln('  '.$this->badge($notice->severity).' <fg=white>'.$notice->message.'</>');
        }

        $io->newLine();
    }

    /**
     * @param array<string, int|string> $meta
     */
    private function renderMeta(SymfonyStyle $io, array $meta): void
    {
        if ([] === $meta) {
            return;
        }

        $labels = array_keys($meta);
        $maxLabelLength = max(array_map(strlen(...), $labels));

        foreach ($meta as $label => $value) {
            $io->writeln($this->formatMetaLine($label, (string) $value, $maxLabelLength));
        }

        $io->newLine();
    }

    private function badge(Severity $severity): string
    {
        return match ($severity) {
            Severity::CRITICAL => self::BADGE_CRIT,
            Severity::FAIL => self::BADGE_FAIL,
            Severity::WARN => self::BADGE_WARN,
            Severity::PASS => self::BADGE_PASS,
        };
    }

    private function formatDetailValue(IssueDetail $detail): string
    {
        return match ($detail->kind) {
            'example' => $this->formatExample($detail->value),
            'pattern' => $this->formatPattern($detail->value),
            default => $detail->value,
        };
    }

    private function formatExample(string $example): string
    {
        if ('' === $example) {
            return '"" (empty string)';
        }

        $escaped = '';
        $length = \strlen($example);
        for ($i = 0; $i < $length; $i++) {
            $byte = \ord($example[$i]);
            $escaped .= match ($byte) {
                0x0A => '\\n',
                0x0D => '\\r',
                0x09 => '\\t',
                0x5C => '\\\\',
                0x22 => '\\"',
                default => ($byte < 0x20 || $byte > 0x7E)
                    ? \sprintf('\\x%02X', $byte)
                    : $example[$i],
            };
        }

        return '<fg=cyan>"'.$escaped.'"</>';
    }

    private function formatPattern(string $pattern): string
    {
        if ('' === $pattern) {
            return '<fg=cyan>""</>';
        }

        return '<fg=cyan>'.$pattern.'</>';
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->writeln('<fg=cyan;options=bold>RegexParser</> <fg=yellow>'.Regex::VERSION.'</> by Younes ENNAJI');
        $io->newLine();
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $message = 'If RegexParser helps, a GitHub star is appreciated: ';
        $io->writeln('  <fg=gray>'.$message.'https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
    }

    private function formatMetaLine(string $label, string $value, int $maxLabelLength): string
    {
        return \sprintf(
            '<fg=white;options=bold>%s</> : <fg=yellow>%s</>',
            str_pad($label, $maxLabelLength),
            $value,
        );
    }
}
