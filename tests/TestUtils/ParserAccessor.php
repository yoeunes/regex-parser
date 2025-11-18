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

namespace RegexParser\Tests\TestUtils;

use RegexParser\Parser;
use RegexParser\Token;
use RegexParser\TokenType;
use ReflectionClass;

/**
 * Accessor class to expose and manipulate private methods/properties of the Parser for unit testing.
 */
class ParserAccessor
{
    private readonly Parser $parser;
    private readonly ReflectionClass $reflection;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
        $this->reflection = new ReflectionClass($parser);
    }

    /**
     * Calls a private method on the Parser instance.
     *
     * @param string $methodName
     * @param array<mixed> $args
     * @return mixed
     */
    public function callPrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->parser, ...$args);
    }

    /**
     * Sets the private token array (used to simulate the Lexer output).
     *
     * @param array<string|Token> $tokens
     */
    public function setTokens(array $tokens): void
    {
        $processedTokens = [];
        $pos = 0;
        foreach ($tokens as $token) {
            if ($token instanceof Token) {
                $processedTokens[] = $token;
            } else {
                // Créer un token simple T_LITERAL si une simple chaîne est passée
                $processedTokens[] = $this->createToken(TokenType::T_LITERAL, $token, $pos);
            }
            $pos += \strlen($token);
        }
        // Assurer que le T_EOF est présent
        $processedTokens[] = $this->createToken(TokenType::T_EOF, '', $pos);

        $property = $this->reflection->getProperty('tokens');
        $property->setValue($this->parser, $processedTokens);
    }

    /**
     * Sets the current position of the parser in the token stream.
     */
    public function setPosition(int $position): void
    {
        $property = $this->reflection->getProperty('position');
        $property->setValue($this->parser, $position);
    }

    /**
     * Gets the current position of the parser in the token stream.
     */
    public function getPosition(): int
    {
        $property = $this->reflection->getProperty('position');
        return $property->getValue($this->parser);
    }

    /**
     * Utility method to create a Token instance.
     */
    public function createToken(TokenType $type, string $value, int $position): Token
    {
        return new Token($type, $value, $position);
    }

    /**
     * Wrapper for private advance() method.
     */
    public function advance(): void
    {
        $this->callPrivateMethod('advance');
    }

    /**
     * Wrapper for private current() method.
     */
    public function current(): Token
    {
        return $this->callPrivateMethod('current');
    }
}
