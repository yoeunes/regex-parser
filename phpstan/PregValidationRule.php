<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use RegexParser\Regex;

/**
 * Validates regex patterns in preg_* functions using RegexParser.
 *
 * @implements Rule<FuncCall>
 */
final class PregValidationRule implements Rule
{
    /**
     * @var array<string, int> a map of preg_ function names to their pattern argument index
     */
    private const PREG_FUNCTION_MAP = [
        'preg_match' => 0,
        'preg_match_all' => 0,
        'preg_replace' => 0,
        'preg_replace_callback' => 0,
        'preg_split' => 0,
        'preg_grep' => 0,
    ];

    public function getNodeType(): string
    {
        // We subscribe to all function calls.
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            // Dynamic function calls (e.g., $fn()) are ignored.
            return [];
        }

        $functionName = $node->name->toLowerString();
        if (!isset(self::PREG_FUNCTION_MAP[$functionName])) {
            // Not a function we are interested in.
            return [];
        }

        $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
        $args = $node->getArgs();

        if (!isset($args[$patternArgPosition])) {
            // Malformed call (e.g., preg_match()), let PHPStan's native checks handle it.
            return [];
        }

        $patternArg = $args[$patternArgPosition]->value;
        $patternType = $scope->getType($patternArg);

        $constantStrings = $patternType->getConstantStrings();
        if (0 === \count($constantStrings)) {
            // It's a dynamic variable (e.g., preg_match($pattern, ...)), we cannot check it.
            return [];
        }

        $errors = [];
        foreach ($constantStrings as $constantString) {
            $pattern = $constantString->getValue();

            // Use our library's validation façade.
            $result = Regex::validate($pattern);

            if (!$result->isValid) {
                // We found an error!
                $errors[] = RuleErrorBuilder::message(\sprintf('Invalid PCRE pattern: %s', $result->error))
                    ->line($node->getLine())
                    ->tip(\sprintf('This pattern can cause errors or ReDoS. See regex: %s', $pattern))
                    ->identifier('regex.validation')
                    ->build();
            }
        }

        return $errors;
    }
}
