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

namespace RegexParser\Tests\Unit\Lsp\Converter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Lsp\Converter\PositionConverter;

final class PositionConverterTest extends TestCase
{
    #[Test]
    public function test_offset_to_position_first_line(): void
    {
        $converter = new PositionConverter('hello world');

        $position = $converter->offsetToPosition(0);
        $this->assertSame(['line' => 0, 'character' => 0], $position);

        $position = $converter->offsetToPosition(5);
        $this->assertSame(['line' => 0, 'character' => 5], $position);
    }

    #[Test]
    public function test_offset_to_position_second_line(): void
    {
        $converter = new PositionConverter("hello\nworld");

        $position = $converter->offsetToPosition(6);
        $this->assertSame(['line' => 1, 'character' => 0], $position);

        $position = $converter->offsetToPosition(10);
        $this->assertSame(['line' => 1, 'character' => 4], $position);
    }

    #[Test]
    public function test_offset_to_position_multiple_lines(): void
    {
        $converter = new PositionConverter("line1\nline2\nline3");

        $position = $converter->offsetToPosition(0);
        $this->assertSame(['line' => 0, 'character' => 0], $position);

        $position = $converter->offsetToPosition(6);
        $this->assertSame(['line' => 1, 'character' => 0], $position);

        $position = $converter->offsetToPosition(12);
        $this->assertSame(['line' => 2, 'character' => 0], $position);
    }

    #[Test]
    public function test_position_to_offset(): void
    {
        $converter = new PositionConverter("hello\nworld");

        $this->assertSame(0, $converter->positionToOffset(0, 0));
        $this->assertSame(5, $converter->positionToOffset(0, 5));
        $this->assertSame(6, $converter->positionToOffset(1, 0));
        $this->assertSame(10, $converter->positionToOffset(1, 4));
    }

    #[Test]
    public function test_roundtrip_conversion(): void
    {
        $content = "<?php\npreg_match('/test/', \$t);\n";
        $converter = new PositionConverter($content);

        // Test several offsets
        for ($offset = 0; $offset < \strlen($content); $offset++) {
            $position = $converter->offsetToPosition($offset);
            $backToOffset = $converter->positionToOffset($position['line'], $position['character']);
            $this->assertSame($offset, $backToOffset, "Roundtrip failed for offset $offset");
        }
    }

    #[Test]
    public function test_get_line_content(): void
    {
        $converter = new PositionConverter("line1\nline2\nline3");

        $this->assertSame("line1\n", $converter->getLineContent(0));
        $this->assertSame("line2\n", $converter->getLineContent(1));
        $this->assertSame('line3', $converter->getLineContent(2));
    }

    #[Test]
    public function test_is_offset_in_range(): void
    {
        $converter = new PositionConverter("hello\nworld");

        $start = ['line' => 0, 'character' => 2];
        $end = ['line' => 0, 'character' => 5];

        $this->assertFalse($converter->isOffsetInRange(0, $start, $end));
        $this->assertFalse($converter->isOffsetInRange(1, $start, $end));
        $this->assertTrue($converter->isOffsetInRange(2, $start, $end));
        $this->assertTrue($converter->isOffsetInRange(3, $start, $end));
        $this->assertTrue($converter->isOffsetInRange(5, $start, $end));
        $this->assertFalse($converter->isOffsetInRange(6, $start, $end));
    }

    #[Test]
    public function test_is_position_in_byte_range(): void
    {
        $converter = new PositionConverter("hello\nworld");

        // Range from offset 2 to 5 (characters 'llo')
        $this->assertFalse($converter->isPositionInByteRange(0, 0, 2, 5));
        $this->assertFalse($converter->isPositionInByteRange(0, 1, 2, 5));
        $this->assertTrue($converter->isPositionInByteRange(0, 2, 2, 5));
        $this->assertTrue($converter->isPositionInByteRange(0, 3, 2, 5));
        $this->assertTrue($converter->isPositionInByteRange(0, 5, 2, 5));
        $this->assertFalse($converter->isPositionInByteRange(1, 0, 2, 5));
    }

    #[Test]
    public function test_empty_content(): void
    {
        $converter = new PositionConverter('');

        $position = $converter->offsetToPosition(0);
        $this->assertSame(['line' => 0, 'character' => 0], $position);
    }

    #[Test]
    public function test_single_newline(): void
    {
        $converter = new PositionConverter("\n");

        $position = $converter->offsetToPosition(0);
        $this->assertSame(['line' => 0, 'character' => 0], $position);

        $position = $converter->offsetToPosition(1);
        $this->assertSame(['line' => 1, 'character' => 0], $position);
    }
}
