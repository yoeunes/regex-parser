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

namespace RegexParser\Tests\Bridge\Psalm;

use PHPUnit\Framework\TestCase;

final class PregValidationHandlerTest extends TestCase
{
    public function test_it_reports_syntax_and_redos_issues(): void
    {
        $psalmBinary = $this->getPsalmBinary();

        if (!file_exists($psalmBinary)) {
            self::fail('Psalm binary not found. Run "composer install -d tools/psalm" to install test tooling.');
        }

        $command = \sprintf(
            '%s %s --config=%s --output-format=json --no-progress --no-cache %s',
            escapeshellarg(\PHP_BINARY),
            escapeshellarg($psalmBinary),
            escapeshellarg($this->getPsalmConfig()),
            escapeshellarg($this->getFixtureFile()),
        );

        exec($command.' 2>&1', $outputLines, $exitCode);
        $output = trim(implode("\n", $outputLines));

        self::assertNotSame('', $output, 'Psalm output is empty');

        $json = $this->extractJson($output);
        self::assertNotNull($json, 'Psalm did not return JSON output.');

        $issues = json_decode($json, true);
        self::assertIsArray($issues, 'Psalm JSON output expected');

        $types = array_column($issues, 'type');
        self::assertContains('RegexSyntaxIssue', $types);
        self::assertContains('RegexRedosIssue', $types);

        $messages = array_column($issues, 'message');
        self::assertTrue($this->contains($messages, 'Regex syntax error'), 'Expected syntax validation message.');
        self::assertTrue($this->contains($messages, 'ReDoS vulnerability detected'), 'Expected ReDoS validation message.');

        self::assertNotSame(0, $exitCode, 'Psalm should return a non-zero exit code when issues are found.');
    }

    private function getPsalmBinary(): string
    {
        return \dirname(__DIR__, 3).'/tools/psalm/vendor/bin/psalm';
    }

    private function getPsalmConfig(): string
    {
        return __DIR__.'/psalm.xml';
    }

    private function getFixtureFile(): string
    {
        return __DIR__.'/Fixtures/PregFunctions.php';
    }

    private function extractJson(string $output): ?string
    {
        $start = strrpos($output, "\n[");
        if (false !== $start) {
            return substr($output, $start + 1);
        }

        $objectStart = strrpos($output, "\n{");
        if (false !== $objectStart) {
            return substr($output, $objectStart + 1);
        }

        return null;
    }

    /**
     * @param list<string> $messages
     */
    private function contains(array $messages, string $needle): bool
    {
        foreach ($messages as $message) {
            if (false !== stripos((string) $message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
