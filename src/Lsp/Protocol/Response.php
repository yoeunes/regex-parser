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
 * Handles sending JSON-RPC responses to stdout.
 */
final class Response
{
    /**
     * Send a successful response.
     */
    public static function success(int|string $id, mixed $result): void
    {
        self::send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    /**
     * Send an error response.
     */
    public static function error(int|string $id, int $code, string $message, mixed $data = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (null !== $data) {
            $error['data'] = $data;
        }

        self::send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ]);
    }

    /**
     * Send a notification (no response expected).
     *
     * @param array<string, mixed>|null $params
     */
    public static function notification(string $method, ?array $params = null): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if (null !== $params) {
            $message['params'] = $params;
        }

        self::send($message);
    }

    /**
     * Send a raw JSON-RPC message.
     *
     * @param array<string, mixed> $message
     */
    private static function send(array $message): void
    {
        $json = json_encode($message, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            return;
        }

        $contentLength = \strlen($json);
        fwrite(\STDOUT, "Content-Length: {$contentLength}\r\n\r\n{$json}");
        fflush(\STDOUT);
    }
}
