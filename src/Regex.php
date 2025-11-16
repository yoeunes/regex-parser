<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser;

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

/**
 * Provides a simple static façade for accessing the parser's features.
 */
final class Regex
{
    private static ?Parser $parser = null;

    /**
     * Parses a full PCRE regex string into an Abstract Syntax Tree.
     *
     * @throws LexerException|ParserException
     */
    public static function parse(string $regex): RegexNode
    {
        self::$parser ??= new Parser();

        return self::$parser->parse($regex);
    }

    /**
     * Validates the syntax and semantics (e.g., ReDoS, valid backrefs) of a regex.
     */
    public static function validate(string $regex): ValidationResult
    {
        try {
            $ast = self::parse($regex);
            $visitor = new ValidatorNodeVisitor();
            $ast->accept($visitor);

            return new ValidationResult(true);
        } catch (LexerException|ParserException $e) {
            return new ValidationResult(false, $e->getMessage());
        }
    }

    /**
     * Explains the regex in a human-readable format.
     *
     * @throws LexerException|ParserException
     */
    public static function explain(string $regex): string
    {
        $ast = self::parse($regex);
        $visitor = new ExplainVisitor();

        return $ast->accept($visitor);
    }

    /**
     * Generates a random sample string that matches the regex.
     *
     * @throws LexerException|ParserException
     */
    public static function generate(string $regex): string
    {
        $ast = self::parse($regex);
        $visitor = new SampleGeneratorVisitor();

        return $ast->accept($visitor);
    }
}
