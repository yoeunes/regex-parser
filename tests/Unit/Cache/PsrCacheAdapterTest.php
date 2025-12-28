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

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RegexParser\Cache\PsrCacheAdapter;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RegexNode;

final class PsrCacheAdapterTest extends TestCase
{
    public function test_generate_key_uses_prefix_and_hash(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool, 'pfx_');

        $key = $cache->generateKey('/foo/');

        $this->assertStringStartsWith('pfx_', $key);
        $this->assertStringContainsString(hash('sha256', '/foo/'), $key);
    }

    public function test_custom_key_factory(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool, 'pfx_', static fn (string $regex): string => 'custom_'.$regex);

        $key = $cache->generateKey('bar');

        $this->assertSame('pfx_custom_bar', $key);
    }

    public function test_write_and_load_decoded_payload(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);

        $ast = new RegexNode(new LiteralNode('', 0, 0), '', '/', 0, 0);
        $serialized = serialize($ast);
        $payload = <<<PHP
            <?php

            declare(strict_types=1);

            return unserialize({$this->export($serialized)}, ['allowed_classes' => true]);
            PHP;

        $key = $cache->generateKey('foo');
        $cache->write($key, $payload);

        $loaded = $cache->load($key);

        $this->assertInstanceOf(RegexNode::class, $loaded);
        $this->assertInstanceOf(LiteralNode::class, $loaded->pattern);
    }

    public function test_write_falls_back_to_raw_payload_on_error(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);

        $key = $cache->generateKey('broken');
        $cache->write($key, '<?php broken');

        $this->assertSame('<?php broken', $cache->load($key));
    }

    public function test_clear(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);

        $key = $cache->generateKey('foo');
        $raw = "<?php return 'x';";
        $cache->write($key, $raw);
        $this->assertSame($raw, $cache->load($key));

        $cache->clear('foo');
        $this->assertNull($cache->load($key));

        $cache->write($key, "<?php return 'y';");
        $cache->clear();
        $this->assertNull($cache->load($key));
    }

    public function test_get_timestamp_returns_zero(): void
    {
        $cache = new PsrCacheAdapter(new InMemoryPool());

        $this->assertSame(0, $cache->getTimestamp('unused'));
    }

    public function test_decode_payload_returns_null_for_non_regex_node(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);
        $payload = "<?php return unserialize('".serialize('not-a-node')."', ['allowed_classes' => true]);";

        $key = $cache->generateKey('nonnode');
        $cache->write($key, $payload);

        $this->assertSame($payload, $cache->load($key));
    }

    public function test_decode_payload_returns_null_for_malformed_unserialize(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);
        $payload = "<?php return unserialize('some string'";

        $key = $cache->generateKey('malformed');
        $cache->write($key, $payload);

        $this->assertSame($payload, $cache->load($key));
    }

    public function test_decode_payload_returns_null_for_empty_unserialize_arg(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);
        $payload = "<?php return unserialize('', ['allowed_classes' => true]);";

        $key = $cache->generateKey('emptyarg');
        $cache->write($key, $payload);

        $this->assertSame($payload, $cache->load($key));
    }

    public function test_decode_payload_returns_null_for_missing_unserialize_arg(): void
    {
        $pool = new InMemoryPool();
        $cache = new PsrCacheAdapter($pool);
        $payload = "<?php return unserialize(, ['allowed_classes' => true]);";

        $key = $cache->generateKey('missingarg');
        $cache->write($key, $payload);

        $this->assertSame($payload, $cache->load($key));
    }

    private function export(string $value): string
    {
        return var_export($value, true);
    }
}

final class InMemoryPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, InMemoryItem>
     */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ??= new InMemoryItem($key);
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            $key = (string) $key;
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($item instanceof InMemoryItem) {
            $this->items[$item->getKey()] = $item;
        }

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class InMemoryItem implements CacheItemInterface
{
    private bool $hit = false;

    private mixed $value = null;

    public function __construct(private readonly string $key) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
