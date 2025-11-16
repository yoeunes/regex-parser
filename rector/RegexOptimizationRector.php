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
use PhpParser\Node\Stmt\ClassConst;
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

    /**
     * @var string[] constants we know contain regexes
     */
    private const REGEX_CONSTANT_NAMES = [
        'REGEX_OUTSIDE',
        'REGEX_INSIDE',
    ];

    /**
     * @var CompilerNodeVisitor&object{hasChanged: bool, flags: string}
     */
    private ?CompilerNodeVisitor $optimizerVisitor = null;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Optimizes simple regex character classes, e.g., [a-zA-Z0-9_] to \w (if /u flag is not present)',
            [
                new CodeSample(
                    "preg_match('/[a-zA-Z0-9_]+/', \$str);",
                    "preg_match('/\\w+/', \$str);"
                ),
                new CodeSample(
                    "private const MY_REGEX = '/[a-zA-Z0-9_]/u';",
                    "private const MY_REGEX = '/[a-zA-Z0-9_]/u';" // No change
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        // We now target function calls AND class constants.
        return [FuncCall::class, ClassConst::class];
    }

    /**
     * @param FuncCall|ClassConst $node
     */
    public function refactor(Node $node): ?Node
    {
        $stringNode = $this->getRegexStringNode($node);

        if (!$stringNode instanceof String_) {
            // Not a node we can refactor (e.g., dynamic var or wrong constant)
            return null;
        }

        $originalRegexString = $stringNode->value;

        try {
            // 1. Parse the regex into our AST
            $ast = Regex::parse($originalRegexString);

            // 2. Get the stateful optimizer visitor
            $optimizerVisitor = $this->getOptimizerVisitor();
            $optimizerVisitor->hasChanged = false; // Reset state for this run

            // ***THIS IS THE FIX***
            // Pass the flags to the visitor so it can make smart decisions.
            $optimizerVisitor->flags = $ast->flags;

            // 3. "Visit" the AST to get the new pattern string
            $optimizedPattern = $ast->pattern->accept($optimizerVisitor);

            // 4. If changes were made, reconstruct the full regex and update the node
            if ($optimizerVisitor->hasChanged) {
                $newRegexString = $ast->delimiter.$optimizedPattern.$ast->delimiter.$ast->flags;

                // Update the String_ node's value in place.
                // This works whether it's in a FuncCall or a ClassConst.
                $stringNode->value = $newRegexString;

                return $node; // Return the modified node
            }
        } catch (\Throwable $e) {
            // If parsing fails (invalid regex), do nothing.
            // Let the PHPStan rule report it. Rector rules must not crash.
            return null;
        }

        return null;
    }

    /**
     * Extracts the String_ node from the targeted Node type.
     */
    private function getRegexStringNode(Node $node): ?String_
    {
        if ($node instanceof FuncCall) {
            $functionName = $this->getName($node->name);
            if (null === $functionName || !isset(self::PREG_FUNCTION_MAP[$functionName])) {
                return null;
            }
            $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
            $args = $node->getArgs();
            if (!isset($args[$patternArgPosition]) || !$args[$patternArgPosition]->value instanceof String_) {
                return null;
            }

            return $args[$patternArgPosition]->value;
        }

        if ($node instanceof ClassConst) {
            // We only check constants whose names are in our list
            if (!$this->isNames($node, self::REGEX_CONSTANT_NAMES)) {
                return null;
            }

            $const = $node->consts[0]; // Assume one const per declaration
            if (!$const->value instanceof String_) {
                return null;
            }

            return $const->value;
        }

        return null;
    }

    /**
     * Lazily create and cache the stateful visitor.
     *
     * @return CompilerNodeVisitor&object{hasChanged: bool, flags: string}
     */
    private function getOptimizerVisitor(): CompilerNodeVisitor
    {
        return $this->optimizerVisitor ??= new class extends CompilerNodeVisitor {
            public bool $hasChanged = false;

            // ***THIS IS THE FIX***
            // Property to hold the flags of the current regex
            public string $flags = '';

            public function visitCharClass(CharClassNode $node): string
            {
                // ***THIS IS THE FIX***
                // Only perform this optimization if the /u flag is NOT present.
                if (!str_contains($this->flags, 'u')) {
                    if ($this->isFullWordClass($node)) {
                        $this->hasChanged = true;

                        return '\w';
                    }
                }

                // Add more optimizations here...
                // e.g. [0-9] -> \d (which is safe with or without /u)

                return parent::visitCharClass($node);
            }

            private function isFullWordClass(CharClassNode $node): bool
            {
                if ($node->isNegated || 4 !== \count($node->parts)) {
                    return false;
                }
                $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
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

                return !\in_array(false, $partsFound, true);
            }
        };
    }
}
