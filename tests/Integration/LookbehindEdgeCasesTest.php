<?php

declare(strict_types=1);

namespace Yoeunes\RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class LookbehindEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function testFixedLengthLiteralLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=foo)bar/');
        
        $this->assertTrue($result->isValid, 'Fixed-length lookbehind should be valid');
    }

    public function testFixedLengthDigitLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\d{3})test/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthEscapedCharsLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\(\d{2}\))/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthCharClassLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]{5})/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthCharSequenceLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\w\d)/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthWithEscapeLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=test\.)pattern/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthBackslashLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\\\\)/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthSingleCharClassLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[A-Z])/');
        
        $this->assertTrue($result->isValid);
    }

    public function testVariableLengthStarQuantifierIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=a*)b/');
        
        $this->assertFalse($result->isValid, 'Variable-length star quantifier should be invalid');
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthPlusQuantifierIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=a+)b/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthOptionalQuantifierIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=a?)b/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthUnboundedRangeIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=a{1,})b/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthCharClassStarIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]*)test/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthDigitPlusIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=\d+)end/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testVariableLengthAlternationIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=(a|ab))c/');
        
        $this->assertFalse($result->isValid, 'Alternation with different lengths should be invalid in lookbehind');
        $this->assertNotNull($result->error);
    }

    public function testVariableLengthGroupStarIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=(test)*)/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testMixedFixedAndVariableLengthIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=\d{3}a+)/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testLiteralRepeatPlusVariableIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=[a]{3}b*)/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testOptionalGroupIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=(?:test)?)/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
    }

    public function testVariableRangeBoundIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<=\w{0,5})/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testNegativeVariableLookbehindIsInvalid(): void
    {
        $result = $this->regex->validate('/(?<!a+)/');
        
        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function testFixedLengthExactlyThreeChars(): void
    {
        $result = $this->regex->validate('/(?<=abc)/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedLengthMultipleDigits(): void
    {
        $result = $this->regex->validate('/(?<=\d\d\d)/');
        
        $this->assertTrue($result->isValid);
    }

    public function testFixedRangeLookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]{3,3})/');
        
        $this->assertTrue($result->isValid);
    }

    public function testComplexFixedPattern(): void
    {
        $result = $this->regex->validate('/(?<=test\d{2})/');
        
        $this->assertTrue($result->isValid);
    }
}
