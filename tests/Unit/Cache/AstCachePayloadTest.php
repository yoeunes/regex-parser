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

namespace RegexParser\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Cache\FilesystemCache;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\RegexNode;
use RegexParser\Regex;

final class AstCachePayloadTest extends TestCase
{
    /**
     * Shape of the serialized AST, as of Regex::CACHE_VERSION.
     *
     * Bump Regex::CACHE_VERSION and record the new hash here whenever a node
     * gains, loses or renames a property: entries written by an older release
     * would otherwise be restored as objects with uninitialized properties.
     */
    private const NODE_SHAPE = '14a5f7985b2451397f1ed6ae48dddeef';

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/regex-parser-ast-cache-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach ($this->cacheFiles() as $file) {
            @unlink($file);
        }

        foreach ((array) glob($this->cacheDir.'/*', \GLOB_ONLYDIR) as $directory) {
            @rmdir((string) $directory);
        }

        @rmdir($this->cacheDir);
    }

    #[Test]
    public function test_cached_ast_is_valid_php_and_restores_the_tree(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        Regex::create(['cache' => $cache])->parse('/(?<year>\d{4})-\d{2}/u');

        $key = $this->onlyCacheFile();
        $payload = (string) file_get_contents($key);

        $this->assertStringContainsString("!== '".Regex::CACHE_VERSION."'", $payload);

        $restored = include $key;

        $this->assertInstanceOf(RegexNode::class, $restored);
        $this->assertSame('u', $restored->flags);
        $this->assertSame('(?<year>\d{4})-\d{2}', $restored->source);
    }

    #[Test]
    public function test_second_parse_reads_the_ast_back_from_disk(): void
    {
        $pattern = '/^a(b|c)+$/';

        $cache = new FilesystemCache($this->cacheDir);
        Regex::create(['cache' => $cache])->parse($pattern);

        $reader = new FilesystemCache($this->cacheDir);
        $ast = Regex::create(['cache' => $reader])->parse($pattern);

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertSame(['hits' => 1, 'misses' => 0], $reader->getStats());
    }

    #[Test]
    public function test_a_payload_written_by_another_version_is_ignored(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        Regex::create(['cache' => $cache])->parse('/abc/');

        $key = $this->onlyCacheFile();
        $payload = str_replace(
            "!== '".Regex::CACHE_VERSION."'",
            "!== '0.0.0-other'",
            (string) file_get_contents($key),
        );
        file_put_contents($key, $payload);

        $reader = new FilesystemCache($this->cacheDir);

        $this->assertNull($reader->load($key));
        $this->assertSame(['hits' => 0, 'misses' => 1], $reader->getStats());
    }

    #[Test]
    public function test_cache_version_matches_the_current_node_shape(): void
    {
        $this->assertSame(
            self::NODE_SHAPE,
            $this->nodeShape(),
            'The AST node shape changed. Bump Regex::CACHE_VERSION so that stale cache entries are '
            .'ignored, then record the new hash in self::NODE_SHAPE.',
        );
    }

    private function onlyCacheFile(): string
    {
        $files = $this->cacheFiles();
        if ([] === $files) {
            self::fail('No cache file was written.');
        }

        return $files[0];
    }

    /**
     * @return array<int, string>
     */
    private function cacheFiles(): array
    {
        $files = [];
        foreach ((array) glob($this->cacheDir.'/*/*.php') as $file) {
            $files[] = (string) $file;
        }

        return $files;
    }

    /**
     * A fingerprint of every serialized property of every AST node.
     */
    private function nodeShape(): string
    {
        $shape = [];

        foreach ((array) glob(\dirname(__DIR__, 3).'/src/Node/*.php') as $file) {
            $class = 'RegexParser\\Node\\'.basename((string) $file, '.php');
            if (!class_exists($class) || !is_a($class, NodeInterface::class, true)) {
                continue;
            }

            $properties = [];
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $properties[] = $property->getName().':'.((string) $property->getType());
            }

            sort($properties);
            $shape[] = $class.'('.implode(',', $properties).')';
        }

        sort($shape);

        return substr(hash('sha256', implode("\n", $shape)), 0, 32);
    }
}
