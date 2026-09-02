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
use RegexParser\Node\RegexNode;
use RegexParser\Regex;

final class AstCachePayloadTest extends TestCase
{
    /**
     * Fingerprint of the code that builds an AST, as of Regex::CACHE_VERSION.
     *
     * A cached tree is only worth restoring while the current code would build
     * the same one. That stops being true when a node gains or loses a
     * property, and equally when the lexer or the parser reads something
     * differently — a cache written before such a change hands back the old
     * answer for as long as it lives.
     *
     * So: bump Regex::CACHE_VERSION and record the new fingerprint here. The
     * hash covers the code alone, comments and formatting stripped, so
     * rewording a docblock does not cost anyone their cache.
     */
    private const AST_FINGERPRINT = 'f905efd4bba3fc903d02c3f8e4570def';

    /**
     * Everything whose behaviour decides what a pattern parses into.
     */
    private const AST_SOURCES = [
        'src/Lexer.php',
        'src/Parser.php',
        'src/Token.php',
        'src/TokenStream.php',
        'src/Node/*.php',
        'src/Internal/CodePointReader.php',
        'src/Internal/GroupNameReader.php',
        'src/Internal/InlineFlags.php',
        'src/Internal/PatternParser.php',
        'src/Internal/PcreVerb.php',
        'src/Internal/VersionCondition.php',
    ];

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
    public function test_the_cache_version_covers_what_the_parser_currently_builds(): void
    {
        $this->assertSame(
            self::AST_FINGERPRINT,
            $this->astFingerprint(),
            'The code that builds the AST changed. Bump Regex::CACHE_VERSION so that entries written by the '
            .'previous behaviour are ignored, then record the new fingerprint in self::AST_FINGERPRINT.',
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
     * A fingerprint of the code that decides what a pattern parses into.
     *
     * Comments and whitespace are dropped: only what runs can change the tree.
     */
    private function astFingerprint(): string
    {
        $root = \dirname(__DIR__, 3);
        $files = [];

        foreach (self::AST_SOURCES as $source) {
            foreach ((array) glob($root.'/'.$source) as $file) {
                $files[] = (string) $file;
            }
        }

        sort($files);
        $parts = [];

        foreach ($files as $file) {
            $parts[] = substr($file, \strlen($root) + 1)."\0".$this->meaningfulCode((string) file_get_contents($file));
        }

        return substr(hash('sha256', implode("\n", $parts)), 0, 32);
    }

    private function meaningfulCode(string $php): string
    {
        $code = '';

        foreach (token_get_all($php) as $token) {
            if (!\is_array($token)) {
                $code .= $token;

                continue;
            }

            if (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_WHITESPACE], true)) {
                continue;
            }

            $code .= $token[1];
        }

        return $code;
    }
}
