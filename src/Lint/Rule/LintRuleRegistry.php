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

namespace RegexParser\Lint\Rule;

/**
 * Ordered registry of lint rules.
 *
 * The registration order is load-bearing: for each node type, rules run in
 * registry order, and finish() hooks run in registry order after traversal.
 * The default set reproduces the historical LinterNodeVisitor emission order.
 */
final class LintRuleRegistry
{
    /**
     * @var list<LintRuleInterface>
     */
    private array $rules = [];

    public function __construct()
    {
        // Default rules are registered here as they are extracted from
        // LinterNodeVisitor; order must match historical emission order.
    }

    public function register(LintRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return list<LintRuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }
}
