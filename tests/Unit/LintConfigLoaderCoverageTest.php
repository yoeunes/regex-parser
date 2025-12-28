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
use RegexParser\Lint\Command\LintConfigLoader;
use RegexParser\Lint\Command\LintConfigResult;
use RegexParser\Tests\Support\LintFunctionOverrides;

final class LintConfigLoaderCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        LintFunctionOverrides::reset();
    }

    public function test_load_handles_missing_cwd(): void
    {
        $loader = new LintConfigLoader();
        LintFunctionOverrides::queueGetcwd(false);

        $result = $loader->load();

        $this->assertSame([], $result->config);
        $this->assertSame([], $result->files);
    }

    public function test_normalize_lint_config_rejects_invalid_values(): void
    {
        $loader = new LintConfigLoader();
        $ref = new \ReflectionClass($loader);
        $method = $ref->getMethod('normalizeLintConfig');

        $paths = $method->invoke($loader, ['paths' => 1], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $paths);
        $this->assertNotNull($paths->error);

        $exclude = $method->invoke($loader, ['exclude' => 1], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $exclude);
        $this->assertNotNull($exclude->error);

        $jobs = $method->invoke($loader, ['jobs' => 'no'], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $jobs);
        $this->assertNotNull($jobs->error);

        $minSavingsType = $method->invoke($loader, ['minSavings' => 'no'], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $minSavingsType);
        $this->assertNotNull($minSavingsType->error);

        $minSavingsValue = $method->invoke($loader, ['minSavings' => 0], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $minSavingsValue);
        $this->assertNotNull($minSavingsValue->error);

        $format = $method->invoke($loader, ['format' => ''], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $format);
        $this->assertNotNull($format->error);

        $rules = $method->invoke($loader, ['rules' => 'no'], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $rules);
        $this->assertNotNull($rules->error);

        $ruleEntry = $method->invoke($loader, ['rules' => ['redos' => 'yes']], 'cfg.json');
        $this->assertInstanceOf(LintConfigResult::class, $ruleEntry);
        $this->assertNotNull($ruleEntry->error);
    }

    public function test_normalize_string_list_variants(): void
    {
        $loader = new LintConfigLoader();
        $ref = new \ReflectionClass($loader);
        $method = $ref->getMethod('normalizeStringList');

        $single = $method->invoke($loader, 'src', 'cfg.json', 'paths');
        $this->assertInstanceOf(LintConfigResult::class, $single);
        $this->assertSame(['src'], $single->config['paths']);

        $invalidType = $method->invoke($loader, 123, 'cfg.json', 'paths');
        $this->assertInstanceOf(LintConfigResult::class, $invalidType);
        $this->assertNotNull($invalidType->error);

        $invalidEntry = $method->invoke($loader, [''], 'cfg.json', 'paths');
        $this->assertInstanceOf(LintConfigResult::class, $invalidEntry);
        $this->assertNotNull($invalidEntry->error);
    }
}
