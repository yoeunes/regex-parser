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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Console;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Console\LinkFormatter;
use RegexParser\Bridge\Symfony\Console\RelativePathHelper;

final class LinkFormatterTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $envBackup;

    protected function setUp(): void
    {
        $this->envBackup = [
            'TERMINAL_EMULATOR' => $this->getEnvValue('TERMINAL_EMULATOR'),
            'KONSOLE_VERSION' => $this->getEnvValue('KONSOLE_VERSION'),
            'IDEA_INITIAL_DIRECTORY' => isset($_SERVER['IDEA_INITIAL_DIRECTORY']) && \is_string($_SERVER['IDEA_INITIAL_DIRECTORY']) ? $_SERVER['IDEA_INITIAL_DIRECTORY'] : null,
        ];

        $this->clearEnv('TERMINAL_EMULATOR');
        $this->clearEnv('KONSOLE_VERSION');
        unset($_SERVER['IDEA_INITIAL_DIRECTORY']);
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('TERMINAL_EMULATOR', $this->envBackup['TERMINAL_EMULATOR']);
        $this->restoreEnv('KONSOLE_VERSION', $this->envBackup['KONSOLE_VERSION']);

        if (null === $this->envBackup['IDEA_INITIAL_DIRECTORY']) {
            unset($_SERVER['IDEA_INITIAL_DIRECTORY']);
        } else {
            $_SERVER['IDEA_INITIAL_DIRECTORY'] = $this->envBackup['IDEA_INITIAL_DIRECTORY'];
        }
    }

    public function test_it_returns_plain_label_when_ide_missing(): void
    {
        $formatter = new LinkFormatter(null, new RelativePathHelper('/app'));

        $result = $formatter->format('/app/src/File.php', 10, '  10');

        $this->assertSame('  10', $result);
    }

    public function test_it_formats_href_when_terminal_supports_links(): void
    {
        $formatter = new LinkFormatter('vscode://file/%f:%l', new RelativePathHelper('/app'));

        $result = $formatter->format('/app/src/File.php', 10, '  10');

        $this->assertSame('<href=vscode://file//app/src/File.php:10>  10</>', $result);
    }

    public function test_it_falls_back_to_plain_path_when_terminal_is_unsupported(): void
    {
        putenv('TERMINAL_EMULATOR=JetBrains-JediTerm');

        $formatter = new LinkFormatter('vscode://file/%f:%l', new RelativePathHelper('/app'));

        $result = $formatter->format('/app/src/File.php', 10, '  10');

        $this->assertSame('src/File.php:10', $result);
    }

    public function test_it_supports_editor_aliases(): void
    {
        $formatter = new LinkFormatter('phpstorm', new RelativePathHelper('/app'));

        $result = $formatter->format('/app/index.php', 5, '   5');

        $this->assertSame('<href=phpstorm://open?file=/app/index.php&line=5>   5</>', $result);
    }

    public function test_it_resolves_relative_paths_using_base_path(): void
    {
        $formatter = new LinkFormatter('vscode://file/%f:%l', new RelativePathHelper('/app'));

        $result = $formatter->format('src/File.php', 10, '  10');

        $this->assertSame('<href=vscode://file//app/src/File.php:10>  10</>', $result);
    }

    public function test_relative_path_helper_handles_base_path(): void
    {
        $helper = new RelativePathHelper('/var/www/project');

        $this->assertSame('src/Example.php', $helper->getRelativePath('/var/www/project/src/Example.php'));
        $this->assertSame('/tmp/Example.php', $helper->getRelativePath('/tmp/Example.php'));
    }

    private function getEnvValue(string $name): ?string
    {
        $value = getenv($name);

        return false === $value ? null : $value;
    }

    private function clearEnv(string $name): void
    {
        putenv($name);
        unset($_SERVER[$name]);
    }

    private function restoreEnv(string $name, ?string $value): void
    {
        if (null === $value) {
            putenv($name);
            unset($_SERVER[$name]);
        } else {
            putenv($name.'='.$value);
            $_SERVER[$name] = $value;
        }
    }
}
