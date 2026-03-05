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

namespace RegexParser\Tests\Unit\Lsp;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Lsp\Server;
use RegexParser\Regex;

final class ServerTest extends TestCase
{
    #[Test]
    public function test_server_can_be_instantiated(): void
    {
        $server = new Server();

        $this->assertInstanceOf(Server::class, $server);
    }

    #[Test]
    public function test_server_can_be_instantiated_with_regex(): void
    {
        $regex = Regex::create();
        $server = new Server($regex);

        $this->assertInstanceOf(Server::class, $server);
    }

    #[Test]
    public function test_server_is_final(): void
    {
        $reflection = new \ReflectionClass(Server::class);

        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function test_server_has_run_method(): void
    {
        $reflection = new \ReflectionClass(Server::class);

        $this->assertTrue($reflection->hasMethod('run'));
        $this->assertTrue($reflection->getMethod('run')->isPublic());
    }

    #[Test]
    public function test_server_has_private_handle_message_method(): void
    {
        $reflection = new \ReflectionClass(Server::class);

        $this->assertTrue($reflection->hasMethod('handleMessage'));
        $this->assertTrue($reflection->getMethod('handleMessage')->isPrivate());
    }

    #[Test]
    public function test_server_initial_state(): void
    {
        $server = new Server();
        $reflection = new \ReflectionClass($server);

        $initializedProp = $reflection->getProperty('initialized');
        $this->assertFalse($initializedProp->getValue($server));

        $shutdownProp = $reflection->getProperty('shutdown');
        $this->assertFalse($shutdownProp->getValue($server));
    }

    #[Test]
    public function test_server_has_all_handlers(): void
    {
        $server = new Server();
        $reflection = new \ReflectionClass($server);

        $handlers = ['initHandler', 'textDocHandler', 'codeActionHandler', 'completionHandler'];

        foreach ($handlers as $handlerName) {
            $this->assertTrue($reflection->hasProperty($handlerName), "Missing handler: {$handlerName}");
            $prop = $reflection->getProperty($handlerName);
            $this->assertNotNull($prop->getValue($server), "Handler not initialized: {$handlerName}");
        }
    }
}
