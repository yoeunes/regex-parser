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

namespace RegexParser\Cli;

final class VersionResolver
{
    public function resolve(string $fallback = 'dev'): ?string
    {
        $versionFile = \dirname(__DIR__, 2).'/composer.json';
        if (!file_exists($versionFile)) {
            return null;
        }

        $contents = file_get_contents($versionFile);
        if (false === $contents) {
            return null;
        }

        $composerData = json_decode($contents, true);
        if (!\is_array($composerData)) {
            return null;
        }

        $version = $composerData['version'] ?? $fallback;
        if (!\is_string($version) || '' === $version) {
            return $fallback;
        }

        return $version;
    }

    public function getVersionFile(): string
    {
        return \dirname(__DIR__, 2).'/composer.json';
    }
}
