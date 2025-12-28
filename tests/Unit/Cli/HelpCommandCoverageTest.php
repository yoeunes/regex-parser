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

if (!\function_exists(__NAMESPACE__.'\\preg_split')) {
    /**
     * @return array<int, string>|list<array{string, int}>|false
     */
    function preg_split(string $pattern, string $subject, int $limit = -1, int $flags = 0): array|false
    {
        $queue = $GLOBALS['__cli_preg_split_queue'] ?? [];
        if (\is_array($queue) && [] !== $queue) {
            /** @var array<int, string>|list<array{string, int}>|false $next */
            $next = array_shift($queue);
            $GLOBALS['__cli_preg_split_queue'] = $queue;

            return \is_array($next) ? $next : false;
        }

        /* @var array<int, string>|list<array{string, int}>|false */
        return \preg_split($pattern, $subject, $limit, $flags);
    }
}

namespace RegexParser\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\Output;
use RegexParser\Cli\VersionResolver;

final class HelpCommandCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__cli_preg_split_queue']);
    }

    public function test_resolve_invocation_defaults_to_regex(): void
    {
        $command = new HelpCommand(new VersionResolver());
        $method = (new \ReflectionClass($command))->getMethod('resolveInvocation');

        $_SERVER['argv'] = [''];

        $this->assertSame('regex', $method->invoke($command));
    }

    public function test_format_option_returns_raw_when_split_fails(): void
    {
        $command = new HelpCommand(new VersionResolver());
        $method = (new \ReflectionClass($command))->getMethod('formatOption');
        $GLOBALS['__cli_preg_split_queue'] = [false];

        $output = new Output(true, false);
        $result = $method->invoke($command, $output, '--exclude <path>');

        $this->assertSame('--exclude <path>', $result);
    }
}
