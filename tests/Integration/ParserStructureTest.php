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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

class ParserStructureTest extends TestCase
{
    /**
     * Teste la limite de longueur du pattern.
     */
    public function test_parse_exceeds_max_length(): void
    {
        // On configure une limite très basse pour le test
        $parser = new Parser(['max_pattern_length' => 10]);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex pattern exceeds maximum length');

        $parser->parse('/this_is_too_long/');
    }

    /**
     * Teste une regex trop courte (sans délimiteurs).
     */
    public function test_parse_too_short(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex is too short');

        $parser->parse('/'); // Juste un caractère
    }

    /**
     * Teste l'absence de délimiteur de fermeture valide.
     */
    public function test_parse_no_closing_delimiter(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found');

        $parser->parse('/abc'); // Pas de slash final
    }

    /**
     * Teste le cas subtil où le délimiteur final est échappé.
     */
    public function test_parse_escaped_closing_delimiter_at_end(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found');

        // Ici le dernier slash est échappé, donc ce n'est pas un délimiteur valide
        $parser->parse('/abc\/');
    }

    /**
     * Teste l'utilisation de flags inconnus.
     */
    public function test_parse_unknown_flags(): void
    {
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "k"');

        $parser->parse('/abc/k'); // 'k' n'est pas un flag valide
    }
}
