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

use RegexParser\Cli\ConsoleStyle;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\ReDoS\ReDoSSeverity;

final class AnalyzeCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'analyze';
    }

    public function getAliases(): array
    {
        return ['analyse'];
    }

    public function getDescription(): string
    {
        return 'Parse, validate, and analyze ReDoS risk';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex analyze <pattern>\n");

            return 1;
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        $style = new ConsoleStyle($output, $input->globalOptions->visuals);
        $meta = [];
        if (null !== $input->globalOptions->phpVersion) {
            $meta['Target PHP'] = $output->warning('PHP '.$input->globalOptions->phpVersion);
        }

        $style->renderBanner('analyze', $meta);

        try {
            $ast = $regex->parse($pattern);
            $validation = $regex->validate($pattern);
            $analysis = $regex->redos($pattern);
            $explain = $regex->explain($pattern);

            $highlightedPattern = $output->isAnsi()
                ? $ast->accept(new ConsoleHighlighterVisitor())
                : $pattern;

            $steps = 4;
            $style->renderSection('Parsing pattern', 1, $steps);
            $style->renderPattern($highlightedPattern);
            $style->renderKeyValueBlock([
                'Parse' => $output->success('OK'),
            ]);

            if ($style->visualsEnabled()) {
                $output->write("\n");
            }

            $style->renderSection('Validation', 2, $steps);
            $validationStatus = $validation->isValid ? $output->success('OK') : $output->error('INVALID');
            $style->renderKeyValueBlock([
                'Status' => $validationStatus,
            ]);
            if (!$validation->isValid && $validation->error) {
                $output->write('  '.$output->error($validation->error)."\n");
            }

            if ($style->visualsEnabled()) {
                $output->write("\n");
            }

            $style->renderSection('ReDoS analysis', 3, $steps);
            $severityLabel = strtoupper($analysis->severity->value);
            $severityOutput = match ($analysis->severity) {
                ReDoSSeverity::SAFE, ReDoSSeverity::LOW => $output->success($severityLabel),
                ReDoSSeverity::MEDIUM => $output->warning($severityLabel),
                ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL => $output->error($severityLabel),
                ReDoSSeverity::UNKNOWN => $output->info($severityLabel),
            };
            $style->renderKeyValueBlock([
                'Severity' => $severityOutput.' (score '.$analysis->score.')',
            ]);
            if ($analysis->error) {
                $output->write('  '.$output->error('ReDoS error: '.$analysis->error)."\n");
            }

            if ($style->visualsEnabled()) {
                $output->write("\n");
            }

            $style->renderSection('Explanation', 4, $steps);
            $output->write($explain."\n");
        } catch (LexerException|ParserException $e) {
            $output->write('  '.$output->badge('FAIL', Output::WHITE, Output::BG_RED).' '.$output->error('Analyze failed: '.$e->getMessage())."\n");

            return 1;
        }

        return 0;
    }
}
