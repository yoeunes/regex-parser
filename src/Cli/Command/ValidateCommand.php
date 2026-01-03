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

final class ValidateCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'validate';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Validate a regex pattern';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex validate <pattern>\n");

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
        $style->renderBanner('validate', $meta);

        $validation = $regex->validate($pattern);
        $highlightedPattern = $pattern;
        if ($output->isAnsi() && $validation->isValid) {
            try {
                $ast = $regex->parse($pattern);
                $highlightedPattern = $ast->accept(new ConsoleHighlighterVisitor());
            } catch (LexerException|ParserException) {
                $highlightedPattern = $pattern;
            }
        }

        $style->renderSection('Validating pattern', 1, 1);
        $style->renderPattern($highlightedPattern);

        if ($validation->isValid) {
            $style->renderKeyValueBlock([
                'Status' => $output->success('OK'),
            ]);

            return 0;
        }

        $style->renderKeyValueBlock([
            'Status' => $output->error('INVALID'),
        ]);
        if ($validation->error) {
            $output->write('  '.$output->error($validation->error)."\n");
        }

        return 1;
    }
}
