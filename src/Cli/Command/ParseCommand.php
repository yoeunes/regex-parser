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
use RegexParser\NodeVisitor\CompilerNodeVisitor;

final class ParseCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'parse';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Parse and recompile a regex pattern';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex parse <pattern> [--validate]\n");

            return 1;
        }

        $validate = \in_array('--validate', $input->args, true);
        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        try {
            $ast = $regex->parse($pattern);
            $compiled = $ast->accept(new CompilerNodeVisitor());

            $output->write($output->bold("Parse\n"));
            $output->write('  Pattern:    '.$pattern."\n");
            $output->write('  Recompiled: '.$compiled."\n");

            if ($validate) {
                $validation = $regex->validate($pattern);
                $status = $validation->isValid ? $output->success('OK') : $output->error('INVALID');
                $output->write('  Validation: '.$status."\n");
                if (!$validation->isValid && $validation->error) {
                    $output->write('  '.$output->error($validation->error)."\n");
                }
            }
        } catch (LexerException|ParserException $e) {
            $output->write($output->error('Parse failed: '.$e->getMessage()."\n"));

            return 1;
        }

        return 0;
    }
}
