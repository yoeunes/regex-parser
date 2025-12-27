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
use RegexParser\Lint\Command\LintArgumentParser;
use RegexParser\Lint\Command\LintArguments;
use RegexParser\Lint\Formatter\OutputConfiguration;

final class LintArgumentParserTest extends TestCase
{
    public function test_parse_collects_flags_and_paths(): void
    {
        $parser = new LintArgumentParser();

        $result = $parser->parse([
            '--format', 'json',
            '--exclude', 'vendor',
            '--min-savings', '2',
            '--jobs', '3',
            '--no-redos',
            '--no-optimize',
            '--quiet',
            'src',
        ]);

        $this->assertNull($result->error);
        $this->assertFalse($result->help);
        $this->assertInstanceOf(LintArguments::class, $result->arguments);

        $arguments = $result->arguments;
        $this->assertSame(['src'], $arguments->paths);
        $this->assertSame(['vendor'], $arguments->exclude);
        $this->assertSame(2, $arguments->minSavings);
        $this->assertSame(3, $arguments->jobs);
        $this->assertSame('json', $arguments->format);
        $this->assertSame(OutputConfiguration::VERBOSITY_QUIET, $arguments->verbosity);
        $this->assertTrue($arguments->quiet);
        $this->assertFalse($arguments->checkRedos);
        $this->assertTrue($arguments->checkValidation);
        $this->assertFalse($arguments->checkOptimizations);
    }

    public function test_parse_supports_help_flag(): void
    {
        $parser = new LintArgumentParser();

        $result = $parser->parse(['--help']);

        $this->assertTrue($result->help);
        $this->assertNotInstanceOf(LintArguments::class, $result->arguments);
    }

    public function test_parse_reports_missing_format_value(): void
    {
        $parser = new LintArgumentParser();

        $result = $parser->parse(['--format']);

        $this->assertSame('Missing value for --format.', $result->error);
    }

    public function test_parse_reports_unknown_option(): void
    {
        $parser = new LintArgumentParser();

        $result = $parser->parse(['--unknown']);

        $this->assertSame('Unknown option: --unknown', $result->error);
    }
}
