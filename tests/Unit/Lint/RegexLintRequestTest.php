<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexLintRequest;

final class RegexLintRequestTest extends TestCase
{
    public function test_isSourceEnabled_returns_true_when_source_not_disabled(): void
    {
        $request = new RegexLintRequest(['.'], [], 0, [], true, true, true);

        $this->assertTrue($request->isSourceEnabled('any_source'));
    }

    public function test_isSourceEnabled_returns_true_for_specific_source_when_not_in_disabled_list(): void
    {
        $request = new RegexLintRequest(['.'], [], 0, ['other_source'], true, true, true);

        $this->assertTrue($request->isSourceEnabled('my_source'));
    }

    public function test_isSourceEnabled_returns_false_when_source_disabled(): void
    {
        $request = new RegexLintRequest(['.'], [], 0, ['my_source'], true, true, true);

        $this->assertFalse($request->isSourceEnabled('my_source'));
    }

    public function test_isSourceEnabled_is_case_sensitive(): void
    {
        $request = new RegexLintRequest(['.'], [], 0, ['My_Source'], true, true, true);

        $this->assertFalse($request->isSourceEnabled('My_Source'));
        $this->assertTrue($request->isSourceEnabled('my_source'));
    }

    public function test_getDisabledSources_returns_empty_array_by_default(): void
    {
        $request = new RegexLintRequest(['.'], [], 0);

        $this->assertSame([], $request->getDisabledSources());
    }

    public function test_getDisabledSources_returns_configured_sources(): void
    {
        $sources = ['source1', 'source2'];
        $request = new RegexLintRequest(['.'], [], 0, $sources);

        $this->assertSame($sources, $request->getDisabledSources());
    }
}
