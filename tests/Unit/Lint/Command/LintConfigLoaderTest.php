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

namespace RegexParser\Tests\Unit\Lint\Command;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\Command\LintConfigLoader;

final class LintConfigLoaderTest extends TestCase
{
    public function test_load_reads_and_normalizes_config(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        $tempDir = sys_get_temp_dir().'/regex-parser-config-'.uniqid('', true);
        mkdir($tempDir, 0o700, true);

        $configPath = $tempDir.'/regex.json';
        $config = [
            'paths' => ['src'],
            'exclude' => ['vendor'],
            'jobs' => 2,
            'minSavings' => 3,
            'format' => 'console',
            'rules' => [
                'redos' => false,
                'validation' => true,
                'optimization' => false,
            ],
        ];

        file_put_contents($configPath, json_encode($config, \JSON_THROW_ON_ERROR));

        try {
            chdir($tempDir);

            $loader = new LintConfigLoader();
            $result = $loader->load();

            $this->assertNull($result->error);
            $this->assertSame(['src'], $result->config['paths']);
            $this->assertSame(['vendor'], $result->config['exclude']);
            $this->assertSame(2, $result->config['jobs']);
            $this->assertSame(3, $result->config['minSavings']);
            $this->assertSame('console', $result->config['format']);
            $this->assertSame([
                'redos' => false,
                'validation' => true,
                'optimization' => false,
            ], $result->config['rules']);
            $this->assertCount(1, $result->files);
            $expectedPath = realpath($configPath);
            $actualPath = realpath($result->files[0]);
            $this->assertIsString($expectedPath);
            $this->assertIsString($actualPath);
            $this->assertSame($expectedPath, $actualPath);
        } finally {
            chdir($cwd);
            @unlink($configPath);
            @rmdir($tempDir);
        }
    }

    public function test_load_returns_error_on_invalid_config(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        $tempDir = sys_get_temp_dir().'/regex-parser-config-'.uniqid('', true);
        mkdir($tempDir, 0o700, true);

        $configPath = $tempDir.'/regex.json';
        $config = [
            'jobs' => 0,
        ];

        file_put_contents($configPath, json_encode($config, \JSON_THROW_ON_ERROR));

        try {
            chdir($tempDir);

            $loader = new LintConfigLoader();
            $result = $loader->load();

            $this->assertNotNull($result->error);
            $this->assertStringContainsString('Invalid "jobs"', (string) $result->error);
        } finally {
            chdir($cwd);
            @unlink($configPath);
            @rmdir($tempDir);
        }
    }

    public function test_load_returns_error_on_unreadable_config(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        $tempDir = sys_get_temp_dir().'/regex-parser-config-'.uniqid('', true);
        mkdir($tempDir, 0o700, true);

        $configPath = $tempDir.'/regex.json';
        file_put_contents($configPath, json_encode(['paths' => ['src']], \JSON_THROW_ON_ERROR));
        chmod($configPath, 0);

        try {
            chdir($tempDir);

            $loader = new LintConfigLoader();
            $result = $loader->load();

            $this->assertNotNull($result->error);
            $this->assertStringContainsString('Failed to read config file', (string) $result->error);
        } finally {
            chmod($configPath, 0o600);
            chdir($cwd);
            @unlink($configPath);
            @rmdir($tempDir);
        }
    }

    public function test_load_returns_error_on_non_object_json(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        $tempDir = sys_get_temp_dir().'/regex-parser-config-'.uniqid('', true);
        mkdir($tempDir, 0o700, true);

        $configPath = $tempDir.'/regex.json';
        file_put_contents($configPath, json_encode('string', \JSON_THROW_ON_ERROR));

        try {
            chdir($tempDir);

            $loader = new LintConfigLoader();
            $result = $loader->load();

            $this->assertNotNull($result->error);
            $this->assertStringContainsString('Config file must contain a JSON object', (string) $result->error);
        } finally {
            chdir($cwd);
            @unlink($configPath);
            @rmdir($tempDir);
        }
    }

    public function test_load_returns_error_on_numeric_keys(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        $tempDir = sys_get_temp_dir().'/regex-parser-config-'.uniqid('', true);
        mkdir($tempDir, 0o700, true);

        $configPath = $tempDir.'/regex.json';
        file_put_contents($configPath, json_encode(['invalid'], \JSON_THROW_ON_ERROR));

        try {
            chdir($tempDir);

            $loader = new LintConfigLoader();
            $result = $loader->load();

            $this->assertNotNull($result->error);
            $this->assertStringContainsString('Config file must contain a JSON object', (string) $result->error);
        } finally {
            chdir($cwd);
            @unlink($configPath);
            @rmdir($tempDir);
        }
    }
}
