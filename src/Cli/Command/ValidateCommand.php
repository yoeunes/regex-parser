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

        $validation = $regex->validate($pattern);
        if ($validation->isValid) {
            $output->write($output->success('OK').'  '.$pattern."\n");

            return 0;
        }

        $output->write($output->error('INVALID').'  '.$pattern."\n");
        if ($validation->error) {
            $output->write('  '.$output->error($validation->error)."\n");
        }

        return 1;
    }
}
