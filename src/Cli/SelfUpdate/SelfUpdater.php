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

namespace RegexParser\Cli\SelfUpdate;

use RegexParser\Cli\Output;

final class SelfUpdater
{
    public function run(Output $output): void
    {
        $pharPath = \Phar::running(false);
        if ('' === $pharPath) {
            throw new \RuntimeException('Self-update is only supported for phar installs.');
        }

        if (!file_exists($pharPath)) {
            throw new \RuntimeException('Unable to locate the running phar.');
        }

        if (!is_writable($pharPath)) {
            throw new \RuntimeException('The phar file is not writable: '.$pharPath.'.');
        }

        $updateUrl = 'https://github.com/yoeunes/regex-parser/releases/latest/download/regex.phar';
        $checksumUrl = $updateUrl.'.sha256';

        $output->write($output->info('Downloading latest release...')."\n");

        $checksum = $this->parseChecksum($this->fetchRemoteString($checksumUrl));

        $tempBase = tempnam(sys_get_temp_dir(), 'regex-phar-');
        if (false === $tempBase) {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        @unlink($tempBase);
        $tempPath = $tempBase.'.phar';

        $this->downloadFile($updateUrl, $tempPath);

        $hash = hash_file('sha256', $tempPath);
        if (false === $hash) {
            @unlink($tempPath);

            throw new \RuntimeException('Unable to hash the downloaded phar.');
        }

        if (strtolower($hash) !== strtolower($checksum)) {
            @unlink($tempPath);

            throw new \RuntimeException('Checksum verification failed.');
        }

        $this->validateDownloadedPhar($tempPath);

        $permissions = @fileperms($pharPath);
        if (false !== $permissions) {
            @chmod($tempPath, $permissions & 0o777);
        }

        if (!@rename($tempPath, $pharPath)) {
            if (!@copy($tempPath, $pharPath)) {
                @unlink($tempPath);

                throw new \RuntimeException('Unable to replace the existing binary.');
            }
            @unlink($tempPath);
        }

        $output->write($output->success("RegexParser updated successfully.\n"));
    }

    private function downloadFile(string $url, string $destination): void
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 30,
                'user_agent' => 'regex-parser-cli',
            ],
            'https' => [
                'follow_location' => 1,
                'timeout' => 30,
                'user_agent' => 'regex-parser-cli',
            ],
        ]);

        $read = @fopen($url, 'r', false, $context);
        if (false !== $read) {
            $write = @fopen($destination, 'w');
            if (false === $write) {
                fclose($read);

                throw new \RuntimeException('Unable to write to '.$destination.'.');
            }

            stream_copy_to_stream($read, $write);
            fclose($read);
            fclose($write);

            if (!file_exists($destination) || 0 === (int) filesize($destination)) {
                throw new \RuntimeException('Downloaded file is empty.');
            }

            return;
        }

        if (\function_exists('exec')) {
            $escapedUrl = escapeshellarg($url);
            $escapedDestination = escapeshellarg($destination);

            $output = [];
            $exitCode = 1;
            @exec("curl -fsSL {$escapedUrl} -o {$escapedDestination}", $output, $exitCode);
            if (0 === $exitCode) {
                if (!file_exists($destination) || 0 === (int) filesize($destination)) {
                    throw new \RuntimeException('Downloaded file is empty.');
                }

                return;
            }

            $output = [];
            $exitCode = 1;
            @exec("wget -q -O {$escapedDestination} {$escapedUrl}", $output, $exitCode);
            if (0 === $exitCode) {
                if (!file_exists($destination) || 0 === (int) filesize($destination)) {
                    throw new \RuntimeException('Downloaded file is empty.');
                }

                return;
            }
        }

        throw new \RuntimeException('Unable to download '.$url.'.');
    }

    private function fetchRemoteString(string $url): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'regex-update-');
        if (false === $tempFile) {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        try {
            $this->downloadFile($url, $tempFile);
            $data = file_get_contents($tempFile);
            if (false === $data) {
                throw new \RuntimeException('Unable to read downloaded data.');
            }
        } finally {
            @unlink($tempFile);
        }

        return $data;
    }

    private function parseChecksum(string $contents): string
    {
        $line = trim($contents);
        $parts = preg_split('/\s+/', $line);
        $checksum = strtolower($parts[0] ?? '');

        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new \RuntimeException('Invalid checksum format.');
        }

        return $checksum;
    }

    private function validateDownloadedPhar(string $path): void
    {
        try {
            new \Phar($path);
        } catch (\Exception $e) {
            throw new \RuntimeException('Downloaded phar is invalid: '.$e->getMessage());
        }
    }
}
