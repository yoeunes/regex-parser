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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\ProblemType;
use RegexParser\RegexProblem;
use RegexParser\Severity;

final class RegexProblemTest extends TestCase
{
    public function test_construct_with_minimal_parameters(): void
    {
        $problem = new RegexProblem(
            ProblemType::Syntax,
            Severity::Error,
            'Test message',
        );

        $this->assertSame(ProblemType::Syntax, $problem->type);
        $this->assertSame(Severity::Error, $problem->severity);
        $this->assertSame('Test message', $problem->message);
        $this->assertNull($problem->code);
        $this->assertNull($problem->position);
        $this->assertNull($problem->snippet);
        $this->assertNull($problem->suggestion);
        $this->assertNull($problem->docsAnchor);
        $this->assertNull($problem->tip);
    }

    public function test_construct_with_all_parameters(): void
    {
        $problem = new RegexProblem(
            ProblemType::Security,
            Severity::Critical,
            'Critical security issue',
            'regex.redos',
            42,
            'vulnerable pattern',
            'Use atomic groups',
            'redos-prevention',
            'Consider possessive quantifiers',
        );

        $this->assertSame(ProblemType::Security, $problem->type);
        $this->assertSame(Severity::Critical, $problem->severity);
        $this->assertSame('Critical security issue', $problem->message);
        $this->assertSame('regex.redos', $problem->code);
        $this->assertSame(42, $problem->position);
        $this->assertSame('vulnerable pattern', $problem->snippet);
        $this->assertSame('Use atomic groups', $problem->suggestion);
        $this->assertSame('redos-prevention', $problem->docsAnchor);
        $this->assertSame('Consider possessive quantifiers', $problem->tip);
    }

    public function test_to_array_with_minimal_parameters(): void
    {
        $problem = new RegexProblem(
            ProblemType::Lint,
            Severity::Warning,
            'Lint warning',
        );

        $expected = [
            'type' => 'lint',
            'severity' => 'warning',
            'message' => 'Lint warning',
            'code' => null,
            'position' => null,
            'snippet' => null,
            'suggestion' => null,
            'docsAnchor' => null,
            'tip' => null,
        ];

        $this->assertSame($expected, $problem->toArray());
    }

    public function test_to_array_with_all_parameters(): void
    {
        $problem = new RegexProblem(
            ProblemType::Optimization,
            Severity::Info,
            'Optimization opportunity',
            'regex.opt.unused',
            10,
            'unused group',
            'Remove unused group',
            'optimization-tips',
            'Groups can be removed if not referenced',
        );

        $expected = [
            'type' => 'optimization',
            'severity' => 'info',
            'message' => 'Optimization opportunity',
            'code' => 'regex.opt.unused',
            'position' => 10,
            'snippet' => 'unused group',
            'suggestion' => 'Remove unused group',
            'docsAnchor' => 'optimization-tips',
            'tip' => 'Groups can be removed if not referenced',
        ];

        $this->assertSame($expected, $problem->toArray());
    }

    public function test_to_array_with_empty_strings(): void
    {
        $problem = new RegexProblem(
            ProblemType::Semantic,
            Severity::Error,
            'Semantic error',
            '',
            0,
            '',
            '',
            '',
            '',
        );

        $expected = [
            'type' => 'semantic',
            'severity' => 'error',
            'message' => 'Semantic error',
            'code' => '',
            'position' => 0,
            'snippet' => '',
            'suggestion' => '',
            'docsAnchor' => '',
            'tip' => '',
        ];

        $this->assertSame($expected, $problem->toArray());
    }

    public function test_all_problem_types(): void
    {
        $problemTypes = [
            ProblemType::Syntax,
            ProblemType::Semantic,
            ProblemType::Lint,
            ProblemType::Security,
            ProblemType::Optimization,
        ];

        foreach ($problemTypes as $type) {
            $problem = new RegexProblem($type, Severity::Info, 'Test message');

            $array = $problem->toArray();
            $this->assertSame($type->value, $array['type']);
            $this->assertSame('Test message', $array['message']);
        }
    }

    public function test_all_severity_levels(): void
    {
        $severities = [
            Severity::Info,
            Severity::Warning,
            Severity::Error,
            Severity::Critical,
        ];

        foreach ($severities as $severity) {
            $problem = new RegexProblem(ProblemType::Lint, $severity, 'Test message');

            $array = $problem->toArray();
            $this->assertSame($severity->value, $array['severity']);
            $this->assertSame('Test message', $array['message']);
        }
    }

    public function test_zero_position(): void
    {
        $problem = new RegexProblem(
            ProblemType::Syntax,
            Severity::Error,
            'Error at start',
            null,
            0,
        );

        $this->assertSame(0, $problem->position);
        $array = $problem->toArray();
        $this->assertSame(0, $array['position']);
    }

    public function test_negative_position(): void
    {
        $problem = new RegexProblem(
            ProblemType::Syntax,
            Severity::Error,
            'Error before start',
            null,
            -5,
        );

        $this->assertSame(-5, $problem->position);
        $array = $problem->toArray();
        $this->assertSame(-5, $array['position']);
    }

    public function test_to_array_preserves_null_values(): void
    {
        $problem = new RegexProblem(
            ProblemType::Lint,
            Severity::Warning,
            'Warning message',
        );

        $array = $problem->toArray();

        $this->assertNull($array['code']);
        $this->assertNull($array['position']);
        $this->assertNull($array['snippet']);
        $this->assertNull($array['suggestion']);
        $this->assertNull($array['docsAnchor']);
        $this->assertNull($array['tip']);
    }

    public function test_readonly_properties(): void
    {
        $problem = new RegexProblem(
            ProblemType::Security,
            Severity::Critical,
            'Security issue',
            'SEC001',
            100,
            'bad code',
            'fix it',
            'security-docs',
            'security tip',
        );

        // Test that all properties are set correctly
        $this->assertSame(ProblemType::Security, $problem->type);
        $this->assertSame(Severity::Critical, $problem->severity);
        $this->assertSame('Security issue', $problem->message);
        $this->assertSame('SEC001', $problem->code);
        $this->assertSame(100, $problem->position);
        $this->assertSame('bad code', $problem->snippet);
        $this->assertSame('fix it', $problem->suggestion);
        $this->assertSame('security-docs', $problem->docsAnchor);
        $this->assertSame('security tip', $problem->tip);
    }

    public function test_to_array_structure(): void
    {
        $problem = new RegexProblem(
            ProblemType::Syntax,
            Severity::Error,
            'Syntax error',
        );

        $array = $problem->toArray();

        // Test that array has all expected keys
        $expectedKeys = [
            'type',
            'severity',
            'message',
            'code',
            'position',
            'snippet',
            'suggestion',
            'docsAnchor',
            'tip',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertCount(9, $array);
    }

    public function test_to_array_enum_values(): void
    {
        $problem = new RegexProblem(
            ProblemType::Optimization,
            Severity::Info,
            'Optimization',
        );

        $array = $problem->toArray();

        $this->assertSame('optimization', $array['type']);
        $this->assertSame('info', $array['severity']);
    }

    public function test_construct_with_multiline_message(): void
    {
        $message = "Line 1\nLine 2\nLine 3";
        $problem = new RegexProblem(
            ProblemType::Lint,
            Severity::Warning,
            $message,
        );

        $this->assertSame($message, $problem->message);
        $array = $problem->toArray();
        $this->assertSame($message, $array['message']);
    }
}
