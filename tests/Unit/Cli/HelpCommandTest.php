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

namespace RegexParser\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\GlobalOptions;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;

final class HelpCommandTest extends TestCase
{
    public function test_help_uses_invocation_in_usage_and_examples(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['bin/regex'];

        try {
            $command = new HelpCommand();
            $options = new GlobalOptions(false, null, false, false, null, null);
            $input = new Input('help', [], $options, []);
            $output = new Output(false, false);

            ob_start();
            $command->run($input, $output);
            $text = (string) ob_get_clean();

            $this->assertStringContainsString("Usage:\n  bin/regex <command> [options] <pattern>", $text);
            $this->assertStringContainsString("bin/regex '/a+/'", $text);
            $this->assertStringContainsString("bin/regex analyze '/a+/'", $text);
        } finally {
            if (null === $originalArgv) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
        }
    }
}
