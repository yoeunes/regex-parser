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
use RegexParser\NodeVisitor\RailroadDiagramVisitor;

final class DiagramCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'diagram';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Render an ASCII diagram of the AST';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex diagram <pattern> [--format=ascii]\n");

            return 1;
        }

        $format = 'ascii';
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

        if (!\in_array($format, ['ascii', 'cli'], true)) {
            $output->write($output->error("Error: Unsupported format '{$format}'. Use --format=ascii.\n"));

            return 1;
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        try {
            $ast = $regex->parse($pattern);
            $diagram = $ast->accept(new RailroadDiagramVisitor());
            $output->write($diagram."\n");
        } catch (LexerException|ParserException $e) {
            $output->write($output->error('Diagram failed: '.$e->getMessage()."\n"));

            return 1;
        }

        return 0;
    }
}
