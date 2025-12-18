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

namespace RegexParser\Bridge\Symfony\Extractor;

/**
 * Extracts regex patterns from PHP source files using configured extractor.
 *
 * This class is responsible for discovering and filtering PHP files,
 * then delegating pattern extraction to configured strategy.
 *
 * @internal
 */
final class RegexPatternExtractor
{
    /**
     * @param list<string> $excludePaths
     */
    public function __construct(
        private ExtractorInterface $extractor,
        private array $excludePaths = ['vendor'],
    ) {
    }

    /**
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function extract(array $paths): array
    {
        $phpFiles = $this->collectPhpFiles($paths);
        
        return $this->extractor->extract($phpFiles);
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function collectPhpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if ('' === $path) {
                continue;
            }

            if (is_file($path) && str_ends_with($path, '.php')) {
                $files[] = $path;
                continue;
            }

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }

                $filePath = $file->getPathname();

                // Skip excluded directories
                $excluded = false;
                foreach ($this->excludePaths as $excludePath) {
                    $excludePath = trim($excludePath, '/\\');
                    if ('' === $excludePath) {
                        continue;
                    }
                    if (str_contains($filePath, \DIRECTORY_SEPARATOR.$excludePath.\DIRECTORY_SEPARATOR) || str_starts_with($filePath, $excludePath.\DIRECTORY_SEPARATOR)) {
                        $excluded = true;
                        break;
                    }
                }

                if (!$excluded) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }
}