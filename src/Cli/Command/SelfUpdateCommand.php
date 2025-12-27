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
use RegexParser\Cli\SelfUpdate\SelfUpdater;

final readonly class SelfUpdateCommand implements CommandInterface
{
    public function __construct(private SelfUpdater $updater) {}

    public function getName(): string
    {
        return 'self-update';
    }

    public function getAliases(): array
    {
        return ['selfupdate'];
    }

    public function getDescription(): string
    {
        return 'Update the CLI phar to the latest release';
    }

    public function run(Input $input, Output $output): int
    {
        if (\in_array('--help', $input->args, true)) {
            $output->write("Usage: regex self-update\n");

            return 0;
        }

        try {
            $this->updater->run($output);
        } catch (\RuntimeException $e) {
            $output->write($output->error('Self-update failed: '.$e->getMessage()."\n"));

            return 1;
        }

        return 0;
    }
}
