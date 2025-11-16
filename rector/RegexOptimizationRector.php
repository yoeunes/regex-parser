<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RegexOptimizationRector extends AbstractRector
{
    /**
     * @var array<string, int>
     */
    private const PREG_FUNCTION_MAP = [
        'preg_match' => 0, 'preg_match_all' => 0, 'preg_replace' => 0,
        'preg_replace_callback' => 0, 'preg_split' => 0, 'preg_grep' => 0,
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Optimizes simple regex character classes, e.g., [a-zA-Z0-9_] to \w',
            [
                new CodeSample(
                    "preg_match('/[a-zA-Z0-9_]+/', \$str);",
                    "preg_match('/\\w+/', \$str);"
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        // We target the same function calls.
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        $functionName = $this->getName($node->name);
        if (null === $functionName || !isset(self::PREG_FUNCTION_MAP[$functionName])) {
            return null;
        }

        $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
        $args = $node->getArgs();

        if (!isset($args[$patternArgPosition]) || !$args[$patternArgPosition]->value instanceof String_) {
            // We only refactor literal strings.
            return null;
        }

        $regexStringNode = $args[$patternArgPosition]->value;
        $originalRegexString = $regexStringNode->value;

        try {
            // 1. Parse the regex into our AST
            $ast = Regex::parse($originalRegexString);

            // 2. Create an anonymous transformation Visitor.
            // This visitor inherits from the Compiler to rebuild the string,
            // but overrides specific nodes we want to change.
            $optimizerVisitor = new class extends CompilerNodeVisitor {
                public bool $hasChanged = false;

                public function visitCharClass(CharClassNode $node): string
                {
                    // 3. Transformation Logic
                    if ($this->isFullWordClass($node)) {
                        $this->hasChanged = true;

                        // It's a match! Instead of compiling the children,
                        // we return the optimized shorthand.
                        return '\w';
                    }

                    // Otherwise, let the parent compiler handle it.
                    return parent::visitCharClass($node);
                }

                private function isFullWordClass(CharClassNode $node): bool
                {
                    if ($node->isNegated || 4 !== \count($node->parts)) {
                        return false;
                    }

                    $partsFound = [
                        'a-z' => false,
                        'A-Z' => false,
                        '0-9' => false,
                        '_' => false,
                    ];

                    foreach ($node->parts as $part) {
                        if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                            $range = $part->start->value.'-'.$part->end->value;
                            if (isset($partsFound[$range])) {
                                $partsFound[$range] = true;
                            }
                        } elseif ($part instanceof LiteralNode && '_' === $part->value) {
                            $partsFound['_'] = true;
                        }
                    }

                    // Return true only if all parts were found.
                    return !\in_array(false, $partsFound, true);
                }
            };

            // 4. "Visit" the AST to get the new pattern string
            $optimizedPattern = $ast->pattern->accept($optimizerVisitor);

            // 5. If changes were made, reconstruct the full regex and update the node
            if ($optimizerVisitor->hasChanged) {
                $newRegexString = $ast->delimiter.$optimizedPattern.$ast->delimiter.$ast->flags;
                $args[$patternArgPosition]->value = new String_($newRegexString);

                return $node; // Return the modified FuncCall node
            }
        } catch (\Throwable $e) {
            // If parsing fails (invalid regex), do nothing.
            // Let the PHPStan rule report it. Rector rules must not crash.
            return null;
        }

        return null;
    }
}
