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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\Formatter\ConsoleFormatter;
use RegexParser\Lint\Formatter\OutputConfiguration;
use RegexParser\Lint\Formatter\OutputFormatterInterface;

final class ConsoleFormatterTest extends TestCase
{
    public function test_console_formatter_class_instantiation(): void
    {
        $formatter = new ConsoleFormatter();
        $this->assertInstanceOf(ConsoleFormatter::class, $formatter);
    }

    public function test_console_formatter_with_configuration(): void
    {
        $config = new OutputConfiguration(
            verbosity: OutputConfiguration::VERBOSITY_NORMAL,
            ansi: true,
            showProgress: true,
        );
        $formatter = new ConsoleFormatter(null, $config);
        $this->assertInstanceOf(ConsoleFormatter::class, $formatter);
    }

    public function test_console_formatter_implements_interface(): void
    {
        $formatter = new ConsoleFormatter();
        $this->assertInstanceOf(OutputFormatterInterface::class, $formatter);
    }
}
