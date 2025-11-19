<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

class ParserSubroutineFallbackTest extends TestCase
{
    /**
     * Teste le cas où un token inattendu (non littéral) apparaît dans un nom de subroutine.
     * Ex: (?&...) avec un token T_GROUP_OPEN '(' au lieu d'un littéral.
     */
    public function test_parse_subroutine_name_unexpected_token_type(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Simule: (?& ( ... )
        $tokens = [
            $accessor->createToken(TokenType::T_GROUP_OPEN, '(', 0),
            $accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 1),
        ];
        $accessor->setTokens($tokens);
        $accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token');

        $accessor->callPrivateMethod('parseSubroutineName');
    }

    /**
     * Teste le cas où parseSubroutineName est appelé mais ne trouve aucun nom valide avant la fermeture.
     * Ex: (?&) -> nom vide.
     */
    public function test_parse_subroutine_name_empty(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Simule: ) tout de suite (fin de groupe)
        $tokens = [
            $accessor->createToken(TokenType::T_GROUP_CLOSE, ')', 0),
        ];
        $accessor->setTokens($tokens);
        $accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected subroutine name');

        $accessor->callPrivateMethod('parseSubroutineName');
    }
}
