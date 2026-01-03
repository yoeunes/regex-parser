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

namespace RegexParser\Cli;

use RegexParser\Regex;

final readonly class ConsoleStyle
{
    private const INDENT = '  ';
    private const PATTERN_INDENT = '      ';
    private const ARROW = "\xE2\x86\x92";

    public function __construct(
        private Output $output,
        private bool $visuals = true,
    ) {}

    /**
     * @param array<string, string> $meta
     */
    public function renderBanner(string $command, array $meta = [], ?string $tagline = null): void
    {
        if (!$this->visuals) {
            return;
        }

        $version = Regex::VERSION;
        $this->output->write($this->output->color('RegexParser', Output::CYAN.Output::BOLD).' '.$this->output->warning($version)." by Younes ENNAJI\n");

        if (null !== $tagline && '' !== $tagline) {
            $this->output->write($this->output->dim($tagline)."\n");
        }

        $this->output->write("\n");

        $lines = [
            'Runtime' => 'PHP '.$this->output->warning(\PHP_VERSION),
            'Command' => $this->output->warning($command),
        ];

        foreach ($meta as $label => $value) {
            $lines[$label] = $value;
        }

        $maxLabelLength = max(array_map(strlen(...), array_keys($lines)));
        foreach ($lines as $label => $value) {
            $this->output->write($this->output->bold(str_pad($label, $maxLabelLength)).' : '.$value."\n");
        }

        $this->output->write("\n");
    }

    public function renderSection(string $title, ?int $step = null, ?int $total = null): void
    {
        if (!$this->visuals) {
            return;
        }

        $prefix = '';
        if (null !== $step && null !== $total) {
            $prefix = '['.$step.'/'.$total.'] ';
        }

        $this->output->write(self::INDENT.$this->output->dim($prefix.$title)."\n");
    }

    public function renderPattern(string $pattern, string $label = 'Pattern'): void
    {
        if (!$this->visuals) {
            $this->output->write(self::INDENT.$label.': '.$pattern."\n");

            return;
        }

        $this->output->write(self::INDENT.$this->output->color($label, Output::CYAN.Output::BOLD)."\n");
        $this->output->write(self::PATTERN_INDENT.$this->output->color(self::ARROW.' ', Output::CYAN.Output::BOLD).$pattern."\n");
    }

    /**
     * @param array<string, string> $rows
     */
    public function renderKeyValueBlock(array $rows, int $indent = 2): void
    {
        if ([] === $rows) {
            return;
        }

        $maxLabelLength = max(array_map(strlen(...), array_keys($rows)));
        $prefix = str_repeat(' ', max(0, $indent));

        foreach ($rows as $label => $value) {
            if ($this->visuals) {
                $this->output->write($prefix.$this->output->dim(str_pad($label, $maxLabelLength)).' : '.$value."\n");

                continue;
            }

            $this->output->write($prefix.$label.': '.$value."\n");
        }
    }

    public function visualsEnabled(): bool
    {
        return $this->visuals;
    }
}
