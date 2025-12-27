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
use RegexParser\Cli\VersionResolver;

final readonly class VersionCommand implements CommandInterface
{
    public function __construct(private VersionResolver $versionResolver) {}

    public function getName(): string
    {
        return 'version';
    }

    public function getAliases(): array
    {
        return ['--version', '-v'];
    }

    public function getDescription(): string
    {
        return 'Display version information';
    }

    public function run(Input $input, Output $output): int
    {
        $versionFile = $this->versionResolver->getVersionFile();
        if (!file_exists($versionFile)) {
            $output->write('  '.$output->color('Error:', Output::RED)." unable to read version information.\n");

            return 1;
        }

        $version = $this->versionResolver->resolve('1.0.0') ?? '1.0.0';

        $output->write('RegexParser '.$output->color($version, Output::GREEN)." by Younes ENNAJI\n");
        $output->write("https://github.com/yoeunes/regex-parser\n");

        return 0;
    }
}
