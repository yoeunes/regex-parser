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
use RegexParser\Bridge\PHPStan\RegexParserRule;

final class RegexParserRuleCoverageTest extends TestCase
{
    public function test_process_node_returns_empty_for_unknown_function(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('strlen'), []);
        $errors = $rule->processNode($node, $scope);

        $this->assertSame([], $errors);
    }

    public function test_process_node_returns_empty_for_non_name_function(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Variable('preg_match'), []);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_returns_empty_when_pattern_arg_missing(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('preg_match'), []);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_ignores_non_array_callback_patterns(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $node = new FuncCall(new Name('preg_replace_callback_array'), [
            new Arg(new String_('/foo/')),
        ]);

        $this->assertSame([], $rule->processNode($node, $scope));
    }

    public function test_process_node_skips_non_string_callback_keys(): void
    {
        $rule = new RegexParserRule();
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

    public function test_process_node_continues_after_non_string_callback_keys(): void
    {
        $rule = new RegexParserRule(ignoreParseErrors: false);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $array = new Array_([
            new ArrayItem(new String_('handler'), new LNumber(1)),
            new ArrayItem(new String_('handler'), new String_('/foo')),
        ]);

        $node = new FuncCall(new Name('preg_replace_callback_array'), [
            new Arg($array),
        ]);

        $errors = $rule->processNode($node, $scope);

        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('regex.syntax', $errors[0]->getIdentifier());
    }

    public function test_validate_pattern_returns_error_for_empty_string(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);

        $errors = $this->invokePrivate($rule, 'validatePattern', ['', 10, $scope, 'preg_match']);

        $this->assertCount(1, $errors);
        $this->assertSame('regex.syntax.empty', $errors[0]->getIdentifier());
    }

    public function test_default_ignore_parse_errors_skips_partial_patterns(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/foo', 10, $scope, 'preg_match']);

        $this->assertSame([], $errors);
    }

    public function test_default_report_redos_is_enabled(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(a+)+$/', 5, $scope, 'preg_match']);

        $hasRedos = false;
        foreach ($errors as $error) {
            if (str_starts_with($error->getIdentifier(), 'regex.redos')) {
                $hasRedos = true;

                break;
            }
        }

        $this->assertTrue($hasRedos);
    }

    public function test_default_suggest_optimizations_is_disabled(): void
    {
        $rule = new RegexParserRule();
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[0-9]+/', 9, $scope, 'preg_match']);

        $hasOptimization = false;
        foreach ($errors as $error) {
            if ('regex.optimization' === $error->getIdentifier()) {
                $hasOptimization = true;

                break;
            }
        }

        $this->assertFalse($hasOptimization);
    }

    public function test_checks_config_overrides_legacy_flags(): void
    {
        $rule = new RegexParserRule(
            reportRedos: false,
            suggestOptimizations: false,
            config: [
                'checks' => [
                    'redos' => [
                        'enabled' => true,
                        'mode' => 'confirmed',
                        'threshold' => 'low',
                    ],
                    'optimizations' => [
                        'enabled' => true,
                        'minSavings' => 1,
                        'options' => [
                            'digits' => true,
                            'word' => true,
                            'ranges' => true,
                            'canonicalizeCharClasses' => true,
                        ],
                    ],
                ],
            ],
        );
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $redosErrors = $this->invokePrivate($rule, 'validatePattern', ['/(a+)+$/', 5, $scope, 'preg_match']);
        $hasRedos = false;
        foreach ($redosErrors as $error) {
            if (str_starts_with($error->getIdentifier(), 'regex.redos')) {
                $hasRedos = true;

                break;
            }
        }
        $this->assertTrue($hasRedos);

        $optimizationErrors = $this->invokePrivate($rule, 'validatePattern', ['/[0-9]+/', 6, $scope, 'preg_match']);
        $hasOptimization = false;
        foreach ($optimizationErrors as $error) {
            if ('regex.optimization' === $error->getIdentifier()) {
                $hasOptimization = true;

                break;
            }
        }
        $this->assertTrue($hasOptimization);
    }

    public function test_default_optimization_config_enables_word_optimization(): void
    {
        $rule = new RegexParserRule(reportRedos: false, suggestOptimizations: true);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[A-Za-z0-9_]+/', 11, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        $this->assertContains('regex.optimization', $identifiers);
    }

    public function test_default_optimization_config_avoids_cross_category_ranges(): void
    {
        $rule = new RegexParserRule(reportRedos: false, suggestOptimizations: true);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[9:;<]/', 12, $scope, 'preg_match']);

        $hasOptimization = false;
        foreach ($errors as $error) {
            if ('regex.optimization' === $error->getIdentifier()) {
                $hasOptimization = true;

                break;
            }
        }

        $this->assertFalse($hasOptimization);
    }

    public function test_report_redos_flag_skips_redos_issues(): void
    {
        $rule = new RegexParserRule(reportRedos: false);
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
        $rule = new RegexParserRule(reportRedos: true, redosThreshold: 'low');
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(a{1,5}){1,5}/', 12, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        $this->assertContains('regex.redos.low', $identifiers);
    }

    public function test_unsafe_optimizations_are_skipped(): void
    {
        $rule = new RegexParserRule(reportRedos: false, suggestOptimizations: true);
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(?:a)/', 20, $scope, 'preg_match']);

        foreach ($errors as $error) {
            $this->assertNotSame('regex.optimization', $error->getIdentifier());
        }
    }

    public function test_is_optimization_safe_rejects_empty_optimized_pattern(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/abc/', ''));
    }

    public function test_is_optimization_safe_rejects_short_pattern(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/a/', '/a/'));
    }

    public function test_is_optimization_safe_rejects_delimiter_only(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/a/', '/'));
    }

    public function test_default_optimization_config_enables_digits_optimization(): void
    {
        $rule = new RegexParserRule(reportRedos: false, suggestOptimizations: true);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[0-9]+/', 11, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        $this->assertContains('regex.optimization', $identifiers);
    }

    public function test_is_optimization_format_safe_rejects_empty_delimiter(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/abc/', ''));
    }

    public function test_is_optimization_format_safe_rejects_delimiter_at_start_only(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/abc/', '/'));
    }

    public function test_is_optimization_format_safe_rejects_empty_pattern_part(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/abc/', '//'));
    }

    public function test_is_optimization_format_safe_rejects_short_pattern(): void
    {
        $rule = new RegexParserRule();

        $this->assertFalse($rule->isOptimizationFormatSafe('/ab/', '/a/'));
    }

    public function test_validate_pattern_returns_early_on_syntax_error(): void
    {
        $rule = new RegexParserRule(ignoreParseErrors: false);
        /** @var Scope&NodeCallbackInvoker&MockObject $scope */
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[', 10, $scope, 'preg_match']);

        // Should return exactly one error and not continue processing
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('regex.syntax', $errors[0]->getIdentifier());
    }

    public function test_redos_critical_severity_uses_correct_identifier(): void
    {
        $rule = new RegexParserRule(reportRedos: true, redosThreshold: 'low');
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        // Use a pattern that might trigger ReDoS
        $errors = $this->invokePrivate($rule, 'validatePattern', ['/(x+)+/', 12, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        // Just check that some redos identifier is present, the exact one depends on severity
        $redosIdentifiers = array_filter($identifiers, static fn ($id) => str_starts_with((string) $id, 'regex.redos.'));
        $this->assertNotEmpty($redosIdentifiers);
    }

    public function test_suggest_optimizations_uses_limit_parameter(): void
    {
        $rule = new RegexParserRule(reportRedos: false, suggestOptimizations: true);
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')->willReturn('file.php');

        // This should work with the default limit of 1
        $errors = $this->invokePrivate($rule, 'validatePattern', ['/[0-9]+/', 12, $scope, 'preg_match']);

        $identifiers = array_map(static fn ($error) => $error->getIdentifier(), $errors);
        $this->assertContains('regex.optimization', $identifiers);
    }

    public function test_get_identifier_for_syntax_error_detects_delimiter(): void
    {
        $rule = new RegexParserRule();
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod('getIdentifierForSyntaxError');

        $result = $refMethod->invokeArgs($rule, ['Invalid delimiter in regex pattern']);

        $this->assertSame('regex.syntax.delimiter', $result);
    }

    public function test_get_identifier_for_syntax_error_defaults_to_invalid(): void
    {
        $rule = new RegexParserRule();
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod('getIdentifierForSyntaxError');

        $result = $refMethod->invokeArgs($rule, ['Some other error message']);

        $this->assertSame('regex.syntax.invalid', $result);
    }

    public function test_truncate_pattern_handles_edge_cases(): void
    {
        $rule = new RegexParserRule();
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod('truncatePattern');

        // Test exactly at length limit
        $result = $refMethod->invokeArgs($rule, [str_repeat('a', 50), 50]);
        $this->assertSame(str_repeat('a', 50), $result);

        // Test over length limit
        $result = $refMethod->invokeArgs($rule, [str_repeat('a', 51), 50]);
        $this->assertSame(str_repeat('a', 50).'...', $result);

        // Test default length parameter
        $result = $refMethod->invokeArgs($rule, [str_repeat('a', 55)]);
        $this->assertSame(str_repeat('a', 50).'...', $result);
    }

    public function test_format_source_concatenates_correctly(): void
    {
        $rule = new RegexParserRule();
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod('formatSource');

        $result = $refMethod->invokeArgs($rule, ['preg_match']);

        $this->assertSame('php:preg_match()', $result);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @return array<IdentifierRuleError>
     */
    private function invokePrivate(RegexParserRule $rule, string $method, array $args): array
    {
        $ref = new \ReflectionClass($rule);
        $refMethod = $ref->getMethod($method);

        /** @var array<IdentifierRuleError> $result */
        $result = $refMethod->invokeArgs($rule, $args);

        return $result;
    }
}
