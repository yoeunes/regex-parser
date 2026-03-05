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

namespace RegexParser\Lsp\Protocol;

/**
 * Represents a JSON-RPC message in the Language Server Protocol.
 */
final readonly class Message
{
    /**
     * @param array<string, mixed>|null $params
     */
    public function __construct(
        public string $jsonrpc,
        public ?string $method = null,
        public int|string|null $id = null,
        public ?array $params = null,
        public mixed $result = null,
        public ?array $error = null,
    ) {}

    /**
     * Read a message from stdin following LSP protocol.
     */
    public static function readFromStdin(): ?self
    {
        $headers = [];

        // Read headers until empty line
        while (true) {
            $line = fgets(\STDIN);
            if (false === $line) {
                return null;
            }

            $line = trim($line);
            if ('' === $line) {
                break;
            }

            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $headers[strtolower($matches[1])] = $matches[2];
            }
        }

        if (!isset($headers['content-length'])) {
            return null;
        }

        $contentLength = (int) $headers['content-length'];
        $content = '';

        while (\strlen($content) < $contentLength) {
            $chunk = fread(\STDIN, $contentLength - \strlen($content));
            if (false === $chunk) {
                return null;
            }
            $content .= $chunk;
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return null;
        }

        return new self(
            jsonrpc: $data['jsonrpc'] ?? '2.0',
            method: $data['method'] ?? null,
            id: $data['id'] ?? null,
            params: $data['params'] ?? null,
            result: $data['result'] ?? null,
            error: $data['error'] ?? null,
        );
    }

    /**
     * Check if this is a request (has id and method).
     */
    public function isRequest(): bool
    {
        return null !== $this->id && null !== $this->method;
    }

    /**
     * Check if this is a notification (has method but no id).
     */
    public function isNotification(): bool
    {
        return null === $this->id && null !== $this->method;
    }

    /**
     * Check if this is a response (has id but no method).
     */
    public function isResponse(): bool
    {
        return null !== $this->id && null === $this->method;
    }
}
