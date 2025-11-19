<?php

declare(strict_types=1);

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Regex;

class EdgeCaseValidationTest extends TestCase
{
    public function testBackReferenceToNonExistentGroup(): void
    {
        $result = Regex::create()->validate('/(a)\10/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent group: \10', $result->error);
    }

    public function testBackReferenceToNonExistentNamedGroup(): void
    {
        $result = Regex::create()->validate('/(?<n>a)\k<missing>/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent named group: "missing"', $result->error);
    }

    public function testVariableLengthLookbehind(): void
    {
        $result = Regex::create()->validate('/(?<=a+)/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Variable-length quantifiers (+) are not allowed in lookbehinds', $result->error);
    }
    
    public function testVariableLengthLookbehindWithRange(): void
    {
        $result = Regex::create()->validate('/(?<=a{1,3})/', );
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Variable-length quantifiers ({1,3}) are not allowed in lookbehinds', $result->error);
    }

    public function testInvalidRangeStartGreaterThanEnd(): void
    {
        $result = Regex::create()->validate('/[z-a]/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Invalid range "z-a"', $result->error);
    }

    public function testDuplicateGroupName(): void
    {
        $result = Regex::create()->validate('/(?<n>a)(?<n>b)/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Duplicate group name "n"', $result->error);
    }

    public function testUnconsumedTokens(): void
    {
        // This uses parse(), so it SHOULD throw an exception if Parser is strict.
        // But currently Parser accepts extra flags.
        // Let's switch to validate() and expect failure due to invalid flags if we implement flag validation.
        // Or keep expectException if we fix Parser to throw.
        // For now, let's keep expectException but we know it fails.
        // I will implement flag validation in Parser next.
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown modifier');
        
        Regex::create()->parse('/foo/bar/');
    }
    
    public function testValidOctalIsAccepted(): void
    {
        // \10 should be valid if there are 10 groups
        $pattern = '/(a)(a)(a)(a)(a)(a)(a)(a)(a)(a)\10/';
        $result = Regex::create()->validate($pattern);
        $this->assertTrue($result->isValid, 'Should be valid');
    }
}
