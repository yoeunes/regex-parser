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
use RegexParser\Cache\CacheInterface;
use RegexParser\Cli\Command\AnalyzeCommand;
use RegexParser\Cli\GlobalOptions;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;

final class AnalyzeCommandCoverageTest extends TestCase
{
    public function test_analyze_command_reports_redos_error_when_analysis_fails(): void
    {
        $cache = new class implements CacheInterface {
            private int $loadCalls = 0;

            public function generateKey(string $regex): string
            {
                return 'key';
            }

            public function write(string $key, string $content): void {}

            public function load(string $key): mixed
            {
                $this->loadCalls++;
                if (3 === $this->loadCalls) {
                    throw new \RuntimeException('cache load failed');
                }

                return null;
            }

            public function getTimestamp(string $key): int
            {
                return 0;
            }
        };

        $command = new AnalyzeCommand();
        $input = new Input(
            'analyze',
            ['/foo/'],
            new GlobalOptions(false, null, false, false, null, null),
            ['cache' => $cache],
        );
        $output = new Output(false, false);

        $buffer = $this->captureOutput(static function () use ($command, $input, $output): void {
            $command->run($input, $output);
        });

        $this->assertStringContainsString('ReDoS error:', $buffer);
    }

    /**
     * @param callable(): void $callback
     */
    private function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }
}
