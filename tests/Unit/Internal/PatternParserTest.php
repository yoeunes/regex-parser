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

namespace RegexParser\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Internal\PatternParser;

final class PatternParserTest extends TestCase
{
    public function test_extracts_flags_including_modifier_r_when_supported(): void
    {
        $ref = new \ReflectionClass(PatternParser::class);
        $prop = $ref->getProperty('supportsModifierR');
        $original = $prop->getValue();
        $prop->setValue(null, true);

        try {
            [$pattern, $flags, $delimiter] = PatternParser::extractPatternAndFlags('/a/r');
        } finally {
            $prop->setValue(null, $original);
        }

        $this->assertSame('a', $pattern);
        $this->assertSame('r', $flags);
        $this->assertSame('/', $delimiter);
    }

    public function test_throws_for_missing_closing_delimiter(): void
    {
        $this->expectException(ParserException::class);
        PatternParser::extractPatternAndFlags('/abc');
    }
}
