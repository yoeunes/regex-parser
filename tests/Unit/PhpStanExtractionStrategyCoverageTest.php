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

namespace RegexParser\Tests\Unit;

use PhpParser\ErrorHandler;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use RegexParser\Lint\PhpStanExtractionStrategy;

final class PhpStanExtractionStrategyCoverageTest extends TestCase
{
    public function test_analyze_file_returns_empty_when_parser_missing(): void
    {
        $strategy = $this->newStrategyWithParser(null);
        $method = $this->getPrivateMethod($strategy, 'analyzeFileWithPhpStan');

        $this->assertSame([], $method->invoke($strategy, __FILE__));
    }

    public function test_analyze_file_returns_empty_for_non_array_ast(): void
    {
        $parser = new class implements Parser {
            public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array
            {
                return null;
            }

            public function getTokens(): array
            {
                return [];
            }
        };
        $strategy = $this->newStrategyWithParser($parser);
        $method = $this->getPrivateMethod($strategy, 'analyzeFileWithPhpStan');

        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan');
        if (false === $tempFile) {
            $this->markTestSkipped('Unable to create temp file.');
        }
        file_put_contents($tempFile, '<?php preg_match("/a/", "a");');

        try {
            $this->assertSame([], $method->invoke($strategy, $tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_extract_from_func_call_handles_invalid_nodes(): void
    {
        $strategy = new PhpStanExtractionStrategy();
        $method = $this->getPrivateMethod($strategy, 'extractFromFuncCall');

        $funcCall = new FuncCall(new Variable('preg_match'), []);
        $this->assertSame([], $method->invoke($strategy, $funcCall, __FILE__));

        $funcCall = new FuncCall(new Name('strpos'), []);
        $this->assertSame([], $method->invoke($strategy, $funcCall, __FILE__));

        $funcCall = new FuncCall(new Name('preg_match'), []);
        $this->assertSame([], $method->invoke($strategy, $funcCall, __FILE__));
    }

    public function test_concat_extraction_handles_empty_and_nested_values(): void
    {
        $strategy = new PhpStanExtractionStrategy();

        $extractFromConcat = $this->getPrivateMethod($strategy, 'extractFromConcat');
        $emptyConcat = new Concat(new String_(''), new String_(''));
        $this->assertNull($extractFromConcat->invoke($strategy, $emptyConcat, __FILE__, 'preg_match'));

        $extractStringValue = $this->getPrivateMethod($strategy, 'extractStringValue');
        $nested = new Concat(new String_('a'), new String_('b'));
        $this->assertSame('ab', $extractStringValue->invoke($strategy, $nested));
    }

    private function newStrategyWithParser(?Parser $parser): PhpStanExtractionStrategy
    {
        $ref = new \ReflectionClass(PhpStanExtractionStrategy::class);
        $strategy = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('parser');
        $prop->setValue($strategy, $parser);

        return $strategy;
    }

    private function getPrivateMethod(object $object, string $method): \ReflectionMethod
    {
        $ref = new \ReflectionClass($object);

        return $ref->getMethod($method);
    }
}
