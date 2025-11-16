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

    // Use Dependency Injection
    public function __construct(
        private readonly RegexOptimizationVisitor $optimizerVisitor
    ) {
    }

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

    /**
     * @return array<class-string<Node>>
     */
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
            return null;
        }

        $originalRegexString = $stringNode->value;

        try {
            $ast = Regex::parse($originalRegexString);

            $this->optimizerVisitor->hasChanged = false;
            $this->optimizerVisitor->flags = $ast->flags;

            $optimizedPattern = $ast->pattern->accept($this->optimizerVisitor);

            if ($this->optimizerVisitor->hasChanged) {
                $newRegexString = $ast->delimiter . $optimizedPattern . $ast->delimiter . $ast->flags;
                $stringNode->value = $newRegexString;
                return $node;
            }
        } catch (\Throwable) {
            // If parsing fails, do nothing.
            return null;
        }

        return null;
    }

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
            if (!$this->isNames($node, self::REGEX_CONSTANT_NAMES)) {
                return null;
            }
            $const = $node->consts[0];
            if (!$const->value instanceof String_) {
                return null;
            }

            return $const->value;
        }

        return null;
    }
}
