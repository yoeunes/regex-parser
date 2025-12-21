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

namespace RegexParser\Output;

/**
 * Registry for managing output formatters.
 */
final class FormatterRegistry
{
    /**
     * @var array<string, OutputFormatterInterface>
     */
    private array $formatters = [];

    public function __construct()
    {
        $this->registerDefaultFormatters();
    }

    /**
     * Register a formatter.
     */
    public function register(OutputFormatterInterface $formatter): void
    {
        $this->formatters[$formatter->getName()] = $formatter;
    }

    /**
     * Get a formatter by name.
     */
    public function get(string $name): OutputFormatterInterface
    {
        if (!isset($this->formatters[$name])) {
            throw new \InvalidArgumentException(\sprintf('Formatter "%s" not found. Available formatters: %s',
                $name, implode(', ', array_keys($this->formatters))));
        }

        return $this->formatters[$name];
    }

    /**
     * Check if a formatter is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * Get all registered formatter names.
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->formatters);
    }

    /**
     * Register default formatters.
     */
    private function registerDefaultFormatters(): void
    {
        // Console formatter will be created with analysis service for highlighting
        // Json formatter uses the default config
        $this->register(new ConsoleFormatter());
        $this->register(new JsonFormatter());
    }
}
