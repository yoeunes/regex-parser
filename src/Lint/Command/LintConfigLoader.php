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

namespace RegexParser\Lint\Command;

use RegexParser\ReDoS\ReDoSMode;
use RegexParser\ReDoS\ReDoSSeverity;

final class LintConfigLoader
{
    public function load(): LintConfigResult
    {
        $cwd = getcwd();
        if (false === $cwd) {
            return new LintConfigResult([], []);
        }

        /** @var array<string, mixed> $config */
        $config = [];
        $files = [];
        $paths = [$cwd.'/regex.dist.json', $cwd.'/regex.json'];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $readResult = $this->readLintConfigFile($path);
            if (null !== $readResult->error) {
                return $readResult;
            }

            $normalized = $this->normalizeLintConfig($readResult->config, $path);
            if (null !== $normalized->error) {
                return $normalized;
            }

            /** @var array<string, mixed> $config */
            $config = array_replace_recursive($config, $normalized->config);
            $files[] = $path;
        }

        return new LintConfigResult($config, $files);
    }

    private function readLintConfigFile(string $path): LintConfigResult
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            return new LintConfigResult([], [], 'Failed to read config file: '.$path);
        }

        $decoded = json_decode($contents, true);
        if (null === $decoded && \JSON_ERROR_NONE !== json_last_error()) {
            return new LintConfigResult([], [], 'Invalid JSON in '.$path.': '.json_last_error_msg());
        }

        if (!\is_array($decoded)) {
            return new LintConfigResult([], [], 'Config file must contain a JSON object: '.$path);
        }

        return $this->ensureStringKeyedConfig($decoded, $path);
    }

    /**
     * @param array<mixed, mixed> $config
     */
    private function ensureStringKeyedConfig(array $config, string $path): LintConfigResult
    {
        $normalized = [];
        foreach ($config as $key => $value) {
            if (!\is_string($key)) {
                return new LintConfigResult([], [], 'Config file must contain a JSON object: '.$path);
            }
            $normalized[$key] = $value;
        }

        return new LintConfigResult($normalized, []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function normalizeLintConfig(array $config, string $path): LintConfigResult
    {
        $normalized = [];

        if (\array_key_exists('paths', $config)) {
            $paths = $this->normalizeStringList($config['paths'], $path, 'paths');
            if (null !== $paths->error) {
                return $paths;
            }
            $normalized['paths'] = $paths->config['paths'] ?? [];
        }

        if (\array_key_exists('exclude', $config)) {
            $exclude = $this->normalizeStringList($config['exclude'], $path, 'exclude');
            if (null !== $exclude->error) {
                return $exclude;
            }
            $normalized['exclude'] = $exclude->config['exclude'] ?? [];
        }

        if (\array_key_exists('jobs', $config)) {
            if (!\is_int($config['jobs'])) {
                return new LintConfigResult([], [], 'Invalid "jobs" in '.$path.': expected an integer.');
            }
            if ($config['jobs'] < 1) {
                return new LintConfigResult([], [], 'Invalid "jobs" in '.$path.': value must be >= 1.');
            }
            $normalized['jobs'] = $config['jobs'];
        }

        if (\array_key_exists('minSavings', $config)) {
            if (!\is_int($config['minSavings'])) {
                return new LintConfigResult([], [], 'Invalid "minSavings" in '.$path.': expected an integer.');
            }
            if ($config['minSavings'] < 1) {
                return new LintConfigResult([], [], 'Invalid "minSavings" in '.$path.': value must be >= 1.');
            }
            $normalized['minSavings'] = $config['minSavings'];
        }

        if (\array_key_exists('format', $config)) {
            if (!\is_string($config['format']) || '' === $config['format']) {
                return new LintConfigResult([], [], 'Invalid "format" in '.$path.': expected a non-empty string.');
            }
            $normalized['format'] = $config['format'];
        }

        if (\array_key_exists('redosMode', $config)) {
            if (!\is_string($config['redosMode']) || '' === $config['redosMode']) {
                return new LintConfigResult([], [], 'Invalid "redosMode" in '.$path.': expected a non-empty string.');
            }
            $mode = ReDoSMode::tryFrom(strtolower($config['redosMode']));
            if (null === $mode) {
                return new LintConfigResult([], [], 'Invalid "redosMode" in '.$path.': expected off, theoretical, or confirmed.');
            }
            $normalized['redosMode'] = $mode->value;
        }

        if (\array_key_exists('redosThreshold', $config)) {
            if (!\is_string($config['redosThreshold']) || '' === $config['redosThreshold']) {
                return new LintConfigResult([], [], 'Invalid "redosThreshold" in '.$path.': expected a non-empty string.');
            }
            $threshold = ReDoSSeverity::tryFrom(strtolower($config['redosThreshold']));
            if (null === $threshold) {
                return new LintConfigResult([], [], 'Invalid "redosThreshold" in '.$path.': expected low, medium, high, or critical.');
            }
            $normalized['redosThreshold'] = $threshold->value;
        }

        if (\array_key_exists('redosNoJit', $config)) {
            if (!\is_bool($config['redosNoJit'])) {
                return new LintConfigResult([], [], 'Invalid "redosNoJit" in '.$path.': expected a boolean.');
            }
            $normalized['redosNoJit'] = $config['redosNoJit'];
        }

        if (\array_key_exists('rules', $config)) {
            if (!\is_array($config['rules'])) {
                return new LintConfigResult([], [], 'Invalid "rules" in '.$path.': expected an object.');
            }

            $rules = [];
            foreach (['redos', 'validation', 'optimization'] as $ruleKey) {
                if (!\array_key_exists($ruleKey, $config['rules'])) {
                    continue;
                }
                if (!\is_bool($config['rules'][$ruleKey])) {
                    return new LintConfigResult([], [], 'Invalid "rules.'.$ruleKey.'" in '.$path.': expected a boolean.');
                }
                $rules[$ruleKey] = $config['rules'][$ruleKey];
            }

            if ([] !== $rules) {
                $normalized['rules'] = $rules;
            }
        }

        if (\array_key_exists('ide', $config)) {
            if (!\is_string($config['ide'])) {
                return new LintConfigResult([], [], 'Invalid "ide" in '.$path.': expected a string.');
            }
            $normalized['ide'] = $config['ide'];
        }

        if (\array_key_exists('optimizations', $config)) {
            if (!\is_array($config['optimizations'])) {
                return new LintConfigResult([], [], 'Invalid "optimizations" in '.$path.': expected an object.');
            }

            $optConfig = [];
            $keyMapping = [
                'digits' => 'digits',
                'word' => 'word',
                'ranges' => 'ranges',
                'canonicalizeCharClasses' => 'canonicalizeCharClasses',
                'possessive' => 'autoPossessify',
                'factorize' => 'allowAlternationFactorization',
            ];
            foreach ($keyMapping as $jsonKey => $internalKey) {
                if (\array_key_exists($jsonKey, $config['optimizations'])) {
                    if (!\is_bool($config['optimizations'][$jsonKey])) {
                        return new LintConfigResult([], [], 'Invalid "optimizations.'.$jsonKey.'" in '.$path.': expected a boolean.');
                    }
                    $optConfig[$internalKey] = $config['optimizations'][$jsonKey];
                }
            }

            if (\array_key_exists('minQuantifierCount', $config['optimizations'])) {
                if (!\is_int($config['optimizations']['minQuantifierCount'])) {
                    return new LintConfigResult([], [], 'Invalid "optimizations.minQuantifierCount" in '.$path.': expected an integer.');
                }
                if ($config['optimizations']['minQuantifierCount'] < 2) {
                    return new LintConfigResult([], [], 'Invalid "optimizations.minQuantifierCount" in '.$path.': value must be >= 2.');
                }
                $optConfig['minQuantifierCount'] = $config['optimizations']['minQuantifierCount'];
            }

            if ([] !== $optConfig) {
                $normalized['optimizations'] = $optConfig;
            }
        }

        return new LintConfigResult($normalized, []);
    }

    private function normalizeStringList(mixed $value, string $path, string $key): LintConfigResult
    {
        if (\is_string($value)) {
            $value = [$value];
        }

        if (!\is_array($value)) {
            return new LintConfigResult([], [], 'Invalid "'.$key.'" in '.$path.': expected an array of strings.');
        }

        $normalized = [];
        foreach ($value as $entry) {
            if (!\is_string($entry) || '' === $entry) {
                return new LintConfigResult([], [], 'Invalid "'.$key.'" in '.$path.': expected an array of strings.');
            }
            $normalized[] = $entry;
        }

        return new LintConfigResult([$key => $normalized], []);
    }
}
