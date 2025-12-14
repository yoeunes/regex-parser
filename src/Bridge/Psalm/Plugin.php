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

namespace RegexParser\Bridge\Psalm;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        PregValidationHandler::configure($this->createConfiguration($config));

        $registration->registerHooksFromClass(PregValidationHandler::class);
    }

    private function createConfiguration(?SimpleXMLElement $config): PluginConfiguration
    {
        return new PluginConfiguration(
            ignoreParseErrors: $this->getBoolean($config, 'ignoreParseErrors', true),
            reportRedos: $this->getBoolean($config, 'reportRedos', true),
            redosThreshold: $this->getString($config, 'redosThreshold', 'high'),
            suggestOptimizations: $this->getBoolean($config, 'suggestOptimizations', false),
        );
    }

    private function getBoolean(?SimpleXMLElement $config, string $key, bool $default): bool
    {
        if (null === $config || !isset($config->{$key})) {
            return $default;
        }

        $value = filter_var((string) $config->{$key}, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

        return null === $value ? $default : $value;
    }

    private function getString(?SimpleXMLElement $config, string $key, string $default): string
    {
        if (null === $config || !isset($config->{$key})) {
            return $default;
        }

        $value = strtolower(trim((string) $config->{$key}));

        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : $default;
    }
}
