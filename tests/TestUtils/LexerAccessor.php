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

use RegexParser\Lexer;
use RegexParser\Token;
use RegexParser\TokenType;
use ReflectionClass;
use ReflectionMethod;

/**
 * Accessor class to expose and manipulate private methods/properties of the Lexer for unit testing.
 */
class LexerAccessor
{
    private readonly Lexer $lexer;
    private readonly ReflectionClass $reflection;

    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
        $this->reflection = new ReflectionClass($lexer);
    }

    /**
     * Calls a private method on the Lexer instance.
     *
     * @param string $methodName
     * @param array<mixed> $args
     * @return mixed
     */
    public function callPrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->lexer, ...$args);
    }

    /**
     * Sets the private position property.
     */
    public function setPosition(int $position): void
    {
        $property = $this->reflection->getProperty('position');
        $property->setValue($this->lexer, $position);
    }

    /**
     * Gets the private position property.
     */
    public function getPosition(): int
    {
        $property = $this->reflection->getProperty('position');
        return $property->getValue($this->lexer);
    }

    /**
     * Sets the private inQuoteMode property.
     */
    public function setInQuoteMode(bool $inQuoteMode): void
    {
        $property = $this->reflection->getProperty('inQuoteMode');
        $property->setValue($this->lexer, $inQuoteMode);
    }

    /**
     * Gets the private inQuoteMode property.
     */
    public function getInQuoteMode(): bool
    {
        $property = $this->reflection->getProperty('inQuoteMode');
        return $property->getValue($this->lexer);
    }

    /**
     * Creates a Token instance (useful for mocking complex token structures).
     */
    public function createToken(TokenType $type, string $value, int $position): Token
    {
        return new Token($type, $value, $position);
    }
}
