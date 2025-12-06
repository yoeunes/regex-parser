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
use RegexParser\Regex;

final class ParserInternalErrorTest extends TestCase
{
    /**
     * Teste le cas improbable où le délimiteur n'est pas trouvé dans extractPatternAndFlags
     * (normalement attrapé avant par le check de longueur).
     */
    public function test_extract_pattern_no_delimiter(): void
    {
        $regex = Regex::create();
        $reflection = new \ReflectionClass($regex);
        $method = $reflection->getMethod('extractPatternAndFlags');

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter');

        // On passe une chaîne longue mais sans délimiteur de fin valide
        $method->invoke($regex, '/abcdef');
    }
}
