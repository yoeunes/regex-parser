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

namespace RegexParser\Tests\Unit\Bridge\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\NodeCallbackInvoker;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\PHPStan\PregValidationRule;

final class PregValidationRuleCoverageTest extends TestCase
{
    public function test_process_node_returns_empty_for_unknown_function(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('strlen'), []);
        $errors = $rule->processNode($node, $scope);

        $this->assertSame([], $errors);
    }

    public function test_process_node_returns_empty_for_non_name_function(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Variable('preg_match'), []);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_returns_empty_when_pattern_arg_missing(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('preg_match'), []);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_ignores_non_array_callback_patterns(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('preg_replace_callback_array'), [
            new Arg(new String_('/foo/')),
        ]);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_skips_non_string_callback_keys(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $array = new Array_([
            new ArrayItem(new String_('handler'), new LNumber(1)),
        ]);

        $node = new FuncCall(new Name('preg_replace_callback_array'), [
            new Arg($array),
        ]);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_validate_pattern_returns_error_for_empty_string(): void
    {
        $rule = new PregValidationRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $errors = $this->invokePrivate($rule, 'validatePattern', ['', 10, $scope, 'preg_match']);

        $this->assertCount(1, $errors);
        $this->assertSame('regex.syntax.empty', $errors[0]->getIdentifier());
    }

    public function test_report_redos_flag_skips_redos_issues(): void
    {
        $rule = new PregValidationRule(reportRedos: false);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(a+)+/', 5, $scope, 'preg_match']);

        foreach ($errors as $error) {
            $this->assertStringStartsNotWith('regex.redos', (string) $error->getIdentifier());
        }
    }

    public function test_redos_low_severity_uses_default_identifier(): void
    {
        $rule = new PregValidationRule(reportRedos: true, redosThreshold: 'low');
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(a{1,5}){1,5}/', 12, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        $this->assertContains('regex.redos.low', $identifiers);
    }

    public function test_unsafe_optimizations_are_skipped(): void
    {
        $rule = new PregValidationRule(reportRedos: false, suggestOptimizations: true);
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(?:a)/', 20, $scope, 'preg_match']);

        foreach ($errors as $error) {
            $this->assertNotSame('regex.optimization', $error->getIdentifier());
        }
    }

    public function test_is_optimization_safe_rejects_empty_optimized_pattern(): void
    {
        $rule = new PregValidationRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/abc/', ''));
    }

    public function test_is_optimization_safe_rejects_short_pattern(): void
    {
        $rule = new PregValidationRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/a/', '/a/'));
    }

    public function test_is_optimization_safe_rejects_delimiter_only(): void
    {
        $rule = new PregValidationRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/a/', '/'));
    }

    /**
     * @param array<int, mixed> $args
     *
     * @return array<IdentifierRuleError>
     */
    private function invokePrivate(PregValidationRule $rule, string $method, array $args): array
    {
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod($method);

        /** @var array<IdentifierRuleError> $result */
        $result = $refMethod->invokeArgs($rule, $args);

        return $result;
    }
}
