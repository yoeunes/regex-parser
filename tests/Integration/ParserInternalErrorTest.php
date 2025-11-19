<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser;
use RegexParser\Exception\ParserException;

class ParserInternalErrorTest extends TestCase
{
    /**
     * Teste le cas improbable où le délimiteur n'est pas trouvé dans extractPatternAndFlags
     * (normalement attrapé avant par le check de longueur).
     */
    public function test_extract_pattern_no_delimiter(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('extractPatternAndFlags');

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter');

        // On passe une chaîne longue mais sans délimiteur de fin valide
        $method->invoke($parser, '/abcdef');
    }
}
