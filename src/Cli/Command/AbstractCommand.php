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

use RegexParser\Cli\Output;
use RegexParser\Exception\InvalidRegexOptionException;
use RegexParser\Regex;

abstract class AbstractCommand implements CommandInterface
{
    protected function createRegex(Output $output, array $options): ?Regex
    {
        try {
            return Regex::create($options);
        } catch (InvalidRegexOptionException $e) {
            $output->write($output->error('Invalid option: '.$e->getMessage()."\n"));

            return null;
        }
    }
}
