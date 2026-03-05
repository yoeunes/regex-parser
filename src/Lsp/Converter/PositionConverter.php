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

namespace RegexParser\Lsp\Converter;

/**
 * Converts between byte offsets and LSP line/column positions.
 */
final class PositionConverter
{
    /**
     * @var array<int, int> Line number => byte offset of line start
     */
    private array $lineOffsets = [];

    public function __construct(
        private readonly string $content,
    ) {
        $this->buildLineOffsets();
    }

    /**
     * Convert a byte offset to LSP position (0-indexed line and character).
     *
     * @return array{line: int, character: int}
     */
    public function offsetToPosition(int $offset): array
    {
        $line = 0;
        foreach ($this->lineOffsets as $lineNumber => $lineStart) {
            if ($offset < $lineStart) {
                break;
            }
            $line = $lineNumber;
        }

        $lineStart = $this->lineOffsets[$line] ?? 0;
        $character = $offset - $lineStart;

        return ['line' => $line, 'character' => $character];
    }

    /**
     * Convert LSP position to byte offset.
     */
    public function positionToOffset(int $line, int $character): int
    {
        $lineStart = $this->lineOffsets[$line] ?? 0;

        return $lineStart + $character;
    }

    /**
     * Get the line content at the given line number.
     */
    public function getLineContent(int $line): string
    {
        $start = $this->lineOffsets[$line] ?? 0;
        $end = $this->lineOffsets[$line + 1] ?? \strlen($this->content);

        return substr($this->content, $start, $end - $start);
    }

    /**
     * Check if an offset falls within a range.
     *
     * @param array{line: int, character: int} $start
     * @param array{line: int, character: int} $end
     */
    public function isOffsetInRange(int $offset, array $start, array $end): bool
    {
        $startOffset = $this->positionToOffset($start['line'], $start['character']);
        $endOffset = $this->positionToOffset($end['line'], $end['character']);

        return $offset >= $startOffset && $offset <= $endOffset;
    }

    /**
     * Check if a position falls within a byte range.
     */
    public function isPositionInByteRange(int $line, int $character, int $byteStart, int $byteEnd): bool
    {
        $offset = $this->positionToOffset($line, $character);

        return $offset >= $byteStart && $offset <= $byteEnd;
    }

    private function buildLineOffsets(): void
    {
        $this->lineOffsets[0] = 0;
        $line = 1;

        $length = \strlen($this->content);
        for ($i = 0; $i < $length; $i++) {
            if ("\n" === $this->content[$i]) {
                $this->lineOffsets[$line] = $i + 1;
                $line++;
            }
        }
    }
}
