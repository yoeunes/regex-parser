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

namespace RegexParser\Lsp;

use RegexParser\Lsp\Document\DocumentManager;
use RegexParser\Lsp\Document\RegexFinder;
use RegexParser\Lsp\Handler\CodeActionHandler;
use RegexParser\Lsp\Handler\InitializeHandler;
use RegexParser\Lsp\Handler\TextDocumentHandler;
use RegexParser\Lsp\Protocol\Message;
use RegexParser\Lsp\Protocol\Response;
use RegexParser\Regex;

/**
 * Language Server Protocol server for regex analysis.
 *
 * Provides real-time diagnostics, hover information, and code actions
 * for regex patterns in PHP source files.
 */
final class Server
{
    private bool $initialized = false;

    private bool $shutdown = false;

    private readonly InitializeHandler $initHandler;

    private readonly TextDocumentHandler $textDocHandler;

    private readonly CodeActionHandler $codeActionHandler;

    public function __construct(?Regex $regex = null)
    {
        $regex ??= Regex::create();
        $finder = new RegexFinder();
        $documents = new DocumentManager($finder);

        $this->initHandler = new InitializeHandler();
        $this->textDocHandler = new TextDocumentHandler($documents, $regex);
        $this->codeActionHandler = new CodeActionHandler($documents, $regex);
    }

    /**
     * Run the LSP server main loop.
     */
    public function run(): void
    {
        // Set up stdin/stdout for binary mode
        if (\function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer(\STDIN, 0);
        }

        while (!$this->shutdown) {
            $message = Message::readFromStdin();
            if (null === $message) {
                // EOF or read error
                break;
            }

            $this->handleMessage($message);
        }
    }

    /**
     * Handle a single LSP message.
     */
    private function handleMessage(Message $message): void
    {
        $method = $message->method;
        if (null === $method) {
            return;
        }

        // Special handling for shutdown
        if ('shutdown' === $method) {
            $this->shutdown = true;
            if (null !== $message->id) {
                Response::success($message->id, null);
            }

            return;
        }

        // Exit notification
        if ('exit' === $method) {
            exit($this->shutdown ? 0 : 1);
        }

        // Handle other methods
        match ($method) {
            'initialize' => $this->handleInitialize($message),
            'initialized' => $this->initialized = true,
            'textDocument/didOpen' => $this->textDocHandler->didOpen($message),
            'textDocument/didChange' => $this->textDocHandler->didChange($message),
            'textDocument/didClose' => $this->textDocHandler->didClose($message),
            'textDocument/didSave' => null, // Optional, we handle on change
            'textDocument/hover' => $this->textDocHandler->hover($message),
            'textDocument/codeAction' => $this->codeActionHandler->handle($message),
            '$/cancelRequest' => null, // Ignore cancellation
            default => $this->handleUnknownMethod($message),
        };
    }

    private function handleInitialize(Message $message): void
    {
        $this->initHandler->handle($message);
    }

    private function handleUnknownMethod(Message $message): void
    {
        // Only respond to requests, not notifications
        if ($message->isRequest() && null !== $message->id) {
            Response::error(
                $message->id,
                -32601, // Method not found
                "Method not found: {$message->method}",
            );
        }
    }
}
