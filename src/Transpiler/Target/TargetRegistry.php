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

namespace RegexParser\Transpiler\Target;

use RegexParser\Exception\TranspileException;
use RegexParser\Transpiler\Target\JavaScript\JavaScriptTarget;

/**
 * Registry for available transpilation targets.
 *
 * @internal
 */
final class TargetRegistry
{
    /**
     * @var array<string, TranspileTargetInterface>
     */
    private array $targets = [];

    /**
     * @param array<int, TranspileTargetInterface> $targets
     */
    public function __construct(array $targets = [])
    {
        foreach ($targets as $target) {
            $this->register($target);
        }

        $this->register(new JavaScriptTarget());
    }

    public function register(TranspileTargetInterface $target): void
    {
        $this->targets[$target->getName()] = $target;

        foreach ($target->getAliases() as $alias) {
            $this->targets[$alias] = $target;
        }
    }

    public function get(string $name): TranspileTargetInterface
    {
        $key = strtolower(trim($name));

        if (isset($this->targets[$key])) {
            return $this->targets[$key];
        }

        throw new TranspileException('Unknown transpile target: '.$name.'.');
    }

    /**
     * @return array<int, string>
     */
    public function listTargets(): array
    {
        $names = array_keys($this->targets);
        sort($names);

        return array_values(array_unique($names));
    }
}
