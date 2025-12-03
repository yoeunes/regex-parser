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

namespace RegexParser\Cache;

class NullCache implements RemovableCacheInterface
{
    public function generateKey(string $regex): string
    {
        return hash('sha256', $regex);
    }

    public function write(string $key, string $content): void {}

    public function load(string $key): mixed
    {
        return null;
    }

    public function getTimestamp(string $key): int
    {
        return 0;
    }

    public function clear(?string $regex = null): void {}
}
