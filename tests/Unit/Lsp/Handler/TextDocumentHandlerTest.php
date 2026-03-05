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

namespace RegexParser\Tests\Unit\Lsp\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Lsp\Document\DocumentManager;
use RegexParser\Lsp\Document\RegexFinder;
use RegexParser\Lsp\Handler\TextDocumentHandler;
use RegexParser\Regex;

final class TextDocumentHandlerTest extends TestCase
{
    private DocumentManager $documents;

    protected function setUp(): void
    {
        $this->documents = new DocumentManager(new RegexFinder());
        $handler = new TextDocumentHandler($this->documents, Regex::create());
    }

    #[Test]
    public function test_handler_can_be_instantiated(): void
    {
        $handler = new TextDocumentHandler($this->documents, Regex::create());

        $this->assertInstanceOf(TextDocumentHandler::class, $handler);
    }

    #[Test]
    public function test_handler_requires_dependencies(): void
    {
        $documents = new DocumentManager(new RegexFinder());
        $regex = Regex::create();
        $handler = new TextDocumentHandler($documents, $regex);

        $this->assertInstanceOf(TextDocumentHandler::class, $handler);
    }
}
