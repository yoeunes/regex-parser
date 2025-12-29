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

final class ExplainCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'explain';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Explain a regex pattern in plain language';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex explain <pattern> [--format=text|html]\n");

            return 1;
        }

        $format = 'text';
        for ($i = 0; $i < \count($input->args); $i++) {
            $arg = $input->args[$i];
            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, \strlen('--format='));

                break;
            }
            if ('--format' === $arg) {
                $format = $input->args[$i + 1] ?? $format;
                $i++;
            }
        }

        if (!\in_array($format, ['text', 'html'], true)) {
            $output->write($output->error("Error: Unsupported format '{$format}'. Use --format=text or --format=html.\n"));

            return 1;
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        try {
            $explanation = $regex->explain($pattern, $format);
            $output->write($explanation."\n");
        } catch (LexerException|ParserException $e) {
            $output->write($output->error('Explain failed: '.$e->getMessage()."\n"));

            return 1;
        }

        return 0;
    }
}
