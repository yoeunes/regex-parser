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
use RegexParser\Parser;
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

    private ?Parser $parser = null;

    public function __construct(
        private readonly RegexOptimizationVisitor $optimizerVisitor,
    ) {
    }

    private function getParser(): Parser
    {
        return $this->parser ??= new Parser([]);
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
            $ast = $this->getParser()->parse($originalRegexString);

            // Pass the flags to the visitor so it can make smart decisions.
            $this->optimizerVisitor->flags = $ast->flags;

            // "Visit" the AST to get the new pattern string
            // We clone the visitor to reset its internal state (e.g., inCharClass)
            $optimizer = clone $this->optimizerVisitor;
            $optimizedPattern = $ast->pattern->accept($optimizer);

            $newRegexString = $ast->delimiter.$optimizedPattern.$ast->delimiter.$ast->flags;

            // This is the new, robust check that PHPStan can understand.
            if ($newRegexString !== $originalRegexString) {
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
