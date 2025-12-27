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

use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;

final class AnalyzeCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'analyze';
    }

    public function getAliases(): array
    {
        return [];
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

        try {
            $regex->parse($pattern);
            $validation = $regex->validate($pattern);
            $analysis = $regex->redos($pattern);
            $explain = $regex->explain($pattern);

            $output->write($output->bold("Analyze\n"));
            $output->write('  Pattern:    '.$pattern."\n");
            $output->write('  Parse:      '.$output->success('OK')."\n");
            $validationStatus = $validation->isValid ? $output->success('OK') : $output->error('INVALID');
            $output->write('  Validation: '.$validationStatus."\n");
            if (!$validation->isValid && $validation->error) {
                $output->write('  '.$output->error($validation->error)."\n");
            }

            $severityLabel = strtoupper($analysis->severity->value);
            $output->write('  ReDoS:      '.$output->warning($severityLabel).' (score '.$analysis->score.")\n");
            if ($analysis->error) {
                $output->write('  '.$output->error('ReDoS error: '.$analysis->error)."\n");
            }

            $output->write("\n".$output->bold('Explanation')."\n");
            $output->write($explain."\n");
        } catch (LexerException|ParserException $e) {
            $output->write($output->error('Analyze failed: '.$e->getMessage()."\n"));

            return 1;
        }

        return 0;
    }
}
