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

use RegexParser\Cache\RemovableCacheInterface;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\RegexOptions;

final class ClearCacheCommand extends AbstractCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'clear-cache';
    }

    public function getAliases(): array
    {
        return ['cache:clear'];
    }

    public function getDescription(): string
    {
        return 'Clear the regex parser cache';
    }

    public function run(Input $input, Output $output): int
    {
        $options = RegexOptions::fromArray($input->regexOptions);
        $cache = $options->cache;

        if ($cache instanceof RemovableCacheInterface) {
            $cache->clear();
            $output->write("Cache cleared.\n");
        } else {
            $output->write("No clearable cache configured.\n");
        }

        return 0;
    }
}
